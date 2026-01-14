<?php
/**
 * Scheduled Messages Management
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\ScheduledMessage;
use App\Models\Contact;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                // Validate input
                $validation = validate([
                    'contact_id' => $_POST['contact_id'] ?? '',
                    'message' => sanitize($_POST['message'] ?? ''),
                    'scheduled_at' => $_POST['scheduled_at'] ?? ''
                ], [
                    'contact_id' => 'required',
                    'message' => 'required|min:1|max:4096',
                    'scheduled_at' => 'required'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                // Validate contact exists
                $contactId = intval($_POST['contact_id']);
                $contact = Contact::find($contactId);
                if (!$contact) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Contact not found',
                        'errors' => ['contact_id' => ['Selected contact not found']]
                    ]);
                    exit;
                }
                
                // Validate scheduled date is in the future
                $scheduledAt = $_POST['scheduled_at'];
                $scheduledDateTime = new DateTime($scheduledAt);
                $now = new DateTime();
                
                if ($scheduledDateTime <= $now) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Scheduled time must be in the future',
                        'errors' => ['scheduled_at' => ['Scheduled time must be in the future']]
                    ]);
                    exit;
                }
                
                $scheduled = ScheduledMessage::create([
                    'contact_id' => $contactId,
                    'message' => sanitize($_POST['message']),
                    'scheduled_at' => $scheduledAt,
                    'created_by' => $user->id
                ]);
                echo json_encode(['success' => true, 'scheduled' => $scheduled]);
                break;
            
            case 'cancel':
                $scheduled = ScheduledMessage::findOrFail($_POST['id']);
                $scheduled->update(['status' => 'cancelled']);
                echo json_encode(['success' => true]);
                break;
            
            case 'delete':
                $scheduled = ScheduledMessage::findOrFail($_POST['id']);
                $scheduled->delete();
                echo json_encode(['success' => true]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch scheduled messages
$scheduled = ScheduledMessage::with(['contact', 'creator'])
    ->orderBy('scheduled_at', 'asc')
    ->get();

$contacts = Contact::orderBy('name')->get();

$pageTitle = 'Scheduled Messages';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid scheduled-messages-page">
    <div class="page-header">
        <div>
            <h1>⏰ Scheduled Messages</h1>
            <p>Schedule messages to be sent later</p>
        </div>
        <button class="btn btn-primary" onclick="openScheduleModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Schedule Message
        </button>
    </div>

    <!-- Scheduled Messages Table -->
    <div class="card">
        <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Message</th>
                            <th>Scheduled For</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduled as $msg): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($msg->contact->name ?? 'Unknown'); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($msg->contact->phone_number); ?></small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($msg->message, 0, 50)) . (strlen($msg->message) > 50 ? '...' : ''); ?></small>
                            </td>
                            <td><?php echo $msg->scheduled_at->format('M d, Y H:i'); ?></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'sent' => 'success',
                                    'failed' => 'danger',
                                    'cancelled' => 'secondary'
                                ];
                                $color = $statusColors[$msg->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($msg->status); ?></span>
                            </td>
                            <td>
                                <?php if ($msg->status === 'pending'): ?>
                                <button class="btn btn-sm btn-warning" onclick="cancelScheduled(<?php echo $msg->id; ?>)">
                                    Cancel
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteScheduled(<?php echo $msg->id; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="contact_id" class="form-label">Contact *</label>
                        <select class="form-select crm-select" id="contact_id" name="contact_id">
                            <option value="">Select contact...</option>
                            <?php foreach ($contacts as $contact): ?>
                            <option value="<?php echo $contact->id; ?>">
                                <?php echo htmlspecialchars($contact->name ?? $contact->phone_number); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a contact</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control crm-textarea" id="message" name="message" rows="4"></textarea>
                        <div class="invalid-feedback">Please enter a message</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="scheduled_at" class="form-label">
                            Schedule For (Pakistan Time - PKT) *
                            <small class="text-muted">Current time: <span id="currentTime"></span></small>
                        </label>
                        <input type="datetime-local" class="form-control crm-input" id="scheduled_at" name="scheduled_at">
                        <div class="invalid-feedback">Please select a date and time</div>
                        <small class="form-text text-muted">Message will be sent at the scheduled Pakistan time</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveScheduled()">Schedule Message</button>
            </div>
        </div>
    </div>
</div>

<script>
let scheduleModal;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('scheduleModal');
    if (modalElement) {
        scheduleModal = new bootstrap.Modal(modalElement);
    }
    
    // Set minimum datetime to now
    const datetimeInput = document.getElementById('scheduled_at');
    if (datetimeInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        datetimeInput.min = now.toISOString().slice(0, 16);
    }
});

function openScheduleModal() {
    if (!scheduleModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('scheduleForm').reset();
    scheduleModal.show();
}

// Initialize schedule form validator
let scheduleValidator;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof FormValidator !== 'undefined') {
        scheduleValidator = new FormValidator('scheduleForm', {
            contact_id: ['required'],
            message: ['required', 'min:1', 'max:4096'],
            scheduled_at: ['required']
        });
    } else {
        console.error('FormValidator is not defined. Make sure validation.js is loaded.');
    }
});

function saveScheduled() {
    // Validate form
    if (scheduleValidator && !scheduleValidator.validate()) {
        return;
    }
    
    // Fallback validation
    const contactId = document.getElementById('contact_id').value;
    const message = document.getElementById('message').value.trim();
    const scheduledAt = document.getElementById('scheduled_at').value;
    
    let hasError = false;
    
    if (!contactId) {
        const contactField = document.getElementById('contact_id');
        contactField.classList.add('is-invalid');
        contactField.nextElementSibling.textContent = 'Please select a contact';
        hasError = true;
    }
    
    if (!message) {
        const messageField = document.getElementById('message');
        messageField.classList.add('is-invalid');
        messageField.nextElementSibling.textContent = 'Please enter a message';
        hasError = true;
    }
    
    if (!scheduledAt) {
        const dateField = document.getElementById('scheduled_at');
        dateField.classList.add('is-invalid');
        dateField.nextElementSibling.textContent = 'Please select a date and time';
        hasError = true;
    } else {
        // Validate date is in the future
        const scheduledDate = new Date(scheduledAt);
        const now = new Date();
        if (scheduledDate <= now) {
            const dateField = document.getElementById('scheduled_at');
            dateField.classList.add('is-invalid');
            dateField.nextElementSibling.textContent = 'Scheduled time must be in the future';
            hasError = true;
        }
    }
    
    if (hasError) {
        showToast('Please fill in all required fields correctly', 'error');
        return;
    }
    
    const formData = new FormData(document.getElementById('scheduleForm'));
    formData.append('action', 'create');
    
    fetch('scheduled-messages.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear form and validation
            document.getElementById('scheduleForm').reset();
            const form = document.getElementById('scheduleForm');
            form.classList.remove('was-validated');
            ['contact_id', 'message', 'scheduled_at'].forEach(id => {
                const field = document.getElementById(id);
                if (field) {
                    field.classList.remove('is-invalid', 'is-valid');
                }
            });
            showToast('Message scheduled successfully!', 'success');
            scheduleModal.hide();
            location.reload();
        } else {
            // Handle validation errors from backend
            if (data.errors && scheduleValidator) {
                scheduleValidator.setErrors(data.errors);
            } else {
                showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to schedule message', 'error');
    });
}

function cancelScheduled(id) {
    if (!confirm('Cancel this scheduled message?')) return;
    
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('id', id);
    
    fetch('scheduled-messages.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Scheduled message cancelled!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

// Update current time display
function updateCurrentTime() {
    const now = new Date();
    const options = { 
        timeZone: 'Asia/Karachi',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    };
    const timeString = now.toLocaleString('en-US', options);
    const currentTimeEl = document.getElementById('currentTime');
    if (currentTimeEl) {
        currentTimeEl.textContent = timeString + ' PKT';
    }
}

// Update time every second
setInterval(updateCurrentTime, 1000);
updateCurrentTime();

function deleteScheduled(id) {
    if (!confirm('Delete this scheduled message?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('scheduled-messages.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Scheduled message deleted!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
