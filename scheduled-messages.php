<?php
/**
 * Scheduled Messages Management
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

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
                $scheduled = ScheduledMessage::create([
                    'contact_id' => $_POST['contact_id'],
                    'message' => $_POST['message'],
                    'scheduled_at' => $_POST['scheduled_at'],
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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">⏰ Scheduled Messages</h1>
            <p class="text-muted">Schedule messages to be sent later</p>
        </div>
        <button class="btn btn-primary" onclick="openScheduleModal()">
            <i class="fas fa-plus"></i> Schedule Message
        </button>
    </div>

    <!-- Scheduled Messages Table -->
    <div class="card">
        <div class="card-body">
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
                <form id="scheduleForm">
                    <div class="mb-3">
                        <label for="contact_id" class="form-label">Contact</label>
                        <select class="form-select" id="contact_id" name="contact_id" required>
                            <option value="">Select contact...</option>
                            <?php foreach ($contacts as $contact): ?>
                            <option value="<?php echo $contact->id; ?>">
                                <?php echo htmlspecialchars($contact->name ?? $contact->phone_number); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="scheduled_at" class="form-label">Schedule For</label>
                        <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at" required>
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
    scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
    
    // Set minimum datetime to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('scheduled_at').min = now.toISOString().slice(0, 16);
});

function openScheduleModal() {
    document.getElementById('scheduleForm').reset();
    scheduleModal.show();
}

function saveScheduled() {
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
            showToast('Message scheduled successfully!', 'success');
            scheduleModal.hide();
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
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
