<?php
/**
 * Broadcast Messaging Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\Segment;

// Check if user is authenticated
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
                $broadcast = Broadcast::create([
                    'name' => $_POST['name'],
                    'message' => $_POST['message'],
                    'message_type' => $_POST['message_type'] ?? 'text',
                    'template_name' => $_POST['template_name'] ?? null,
                    'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
                    'status' => !empty($_POST['scheduled_at']) ? 'scheduled' : 'draft',
                    'created_by' => $user->id
                ]);
                
                // Add recipients based on filters
                $recipients = [];
                if (!empty($_POST['recipient_filter'])) {
                    $filter = $_POST['recipient_filter'];
                    
                    if ($filter === 'all') {
                        $contacts = Contact::all();
                    } elseif (str_starts_with($filter, 'tag_')) {
                        $tagId = str_replace('tag_', '', $filter);
                        $contacts = Tag::find($tagId)->contacts;
                    } elseif (str_starts_with($filter, 'segment_')) {
                        $segmentId = str_replace('segment_', '', $filter);
                        $contacts = Segment::find($segmentId)->getContacts();
                    } elseif (str_starts_with($filter, 'stage_')) {
                        $stage = str_replace('stage_', '', $filter);
                        $contacts = Contact::where('stage', $stage)->get();
                    } else {
                        $contacts = collect();
                    }
                    
                    foreach ($contacts as $contact) {
                        BroadcastRecipient::create([
                            'broadcast_id' => $broadcast->id,
                            'contact_id' => $contact->id,
                            'status' => 'pending'
                        ]);
                    }
                    
                    $broadcast->update(['total_recipients' => $contacts->count()]);
                }
                
                echo json_encode(['success' => true, 'broadcast' => $broadcast]);
                break;
            
            case 'update':
                $broadcast = Broadcast::findOrFail($_POST['id']);
                
                if ($broadcast->status !== 'draft' && $broadcast->status !== 'scheduled') {
                    throw new Exception('Cannot edit a broadcast that has been sent');
                }
                
                $broadcast->update([
                    'name' => $_POST['name'],
                    'message' => $_POST['message'],
                    'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null,
                    'status' => !empty($_POST['scheduled_at']) ? 'scheduled' : 'draft'
                ]);
                
                echo json_encode(['success' => true, 'broadcast' => $broadcast]);
                break;
            
            case 'get':
                $broadcast = Broadcast::findOrFail($_POST['id']);
                echo json_encode(['success' => true, 'broadcast' => $broadcast]);
                break;
            
            case 'send':
                $broadcast = Broadcast::findOrFail($_POST['id']);
                
                if ($broadcast->status !== 'draft' && $broadcast->status !== 'scheduled') {
                    throw new Exception('Broadcast has already been sent or is in progress');
                }
                
                $broadcast->update([
                    'status' => 'sending',
                    'started_at' => now()
                ]);
                
                // Queue for background processing (in production, use a job queue)
                echo json_encode(['success' => true, 'message' => 'Broadcast started']);
                break;
            
            case 'cancel':
                $broadcast = Broadcast::findOrFail($_POST['id']);
                $broadcast->update(['status' => 'cancelled']);
                echo json_encode(['success' => true]);
                break;
            
            case 'delete':
                $broadcast = Broadcast::findOrFail($_POST['id']);
                if ($broadcast->status === 'sending') {
                    throw new Exception('Cannot delete a broadcast that is currently sending');
                }
                $broadcast->delete();
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

// Fetch broadcasts
$broadcasts = Broadcast::with('creator')
    ->orderBy('created_at', 'desc')
    ->get();

// Get filters data
$tags = Tag::orderBy('name')->get();
$segments = Segment::orderBy('name')->get();
// Get all unique stages from contacts
$stages = Contact::select('stage')
    ->distinct()
    ->whereNotNull('stage')
    ->pluck('stage')
    ->toArray();
// Add default stages if empty
if (empty($stages)) {
    $stages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'customer', 'lost'];
} else {
    // Ensure default stages are included
    $defaultStages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'customer', 'lost'];
    $stages = array_unique(array_merge($stages, $defaultStages));
    sort($stages);
}

// Render page
$pageTitle = 'Broadcast Messaging';
$pageDescription = 'Send messages to multiple contacts at once';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">📢 Broadcast Messaging</h1>
            <p class="text-muted">Send messages to multiple contacts at once</p>
        </div>
        <button class="btn btn-primary" onclick="openBroadcastModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            New Broadcast
        </button>
    </div>

    <!-- Broadcasts Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Recipients</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Success Rate</th>
                            <th>Scheduled/Started</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($broadcasts as $broadcast): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($broadcast->name); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars(substr($broadcast->message, 0, 40)) . '...'; ?></small>
                            </td>
                            <td><?php echo $broadcast->total_recipients; ?></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'draft' => 'secondary',
                                    'scheduled' => 'info',
                                    'sending' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'cancelled' => 'dark'
                                ];
                                $color = $statusColors[$broadcast->status] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo ucfirst($broadcast->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($broadcast->total_recipients > 0): ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" style="width: <?php echo ($broadcast->sent_count / $broadcast->total_recipients) * 100; ?>%">
                                        <?php echo $broadcast->sent_count; ?>/<?php echo $broadcast->total_recipients; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($broadcast->sent_count > 0): ?>
                                <span class="badge bg-<?php echo $broadcast->success_rate >= 90 ? 'success' : ($broadcast->success_rate >= 70 ? 'warning' : 'danger'); ?>">
                                    <?php echo $broadcast->success_rate; ?>%
                                </span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ($broadcast->started_at) {
                                    echo $broadcast->started_at->format('M d, Y H:i');
                                } elseif ($broadcast->scheduled_at) {
                                    echo $broadcast->scheduled_at->format('M d, Y H:i');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($broadcast->status === 'draft' || $broadcast->status === 'scheduled'): ?>
                                <button class="btn btn-sm btn-primary" onclick="editBroadcast(<?php echo $broadcast->id; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="sendBroadcast(<?php echo $broadcast->id; ?>)" title="Send">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($broadcast->status === 'sending'): ?>
                                <button class="btn btn-sm btn-warning" onclick="cancelBroadcast(<?php echo $broadcast->id; ?>)" title="Cancel">
                                    <i class="fas fa-stop"></i>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-primary" onclick="viewBroadcast(<?php echo $broadcast->id; ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($broadcast->status !== 'sending'): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBroadcast(<?php echo $broadcast->id; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="broadcastForm">
                    <div class="mb-3">
                        <label for="broadcast_name" class="form-label">Broadcast Name</label>
                        <input type="text" class="form-control" id="broadcast_name" name="name" required placeholder="e.g., Monthly Newsletter">
                    </div>
                    
                    <div class="mb-3">
                        <label for="recipient_filter" class="form-label">Recipients</label>
                        <select class="form-select" id="recipient_filter" name="recipient_filter" required>
                            <option value="">Select recipients...</option>
                            <option value="all">All Contacts</option>
                            <optgroup label="Tags">
                                <?php foreach ($tags as $tag): ?>
                                <option value="tag_<?php echo $tag->id; ?>">
                                    🏷️ <?php echo htmlspecialchars($tag->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Segments">
                                <?php foreach ($segments as $segment): ?>
                                <option value="segment_<?php echo $segment->id; ?>">
                                    📊 <?php echo htmlspecialchars($segment->name); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Stages">
                                <?php foreach ($stages as $stage): ?>
                                <option value="stage_<?php echo $stage; ?>">
                                    📌 <?php echo ucfirst($stage); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="broadcast_message" class="form-label">Message</label>
                        <textarea class="form-control" id="broadcast_message" name="message" rows="5" required placeholder="Enter your message here..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="scheduled_at" class="form-label">Schedule (Optional)</label>
                        <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at">
                        <small class="text-muted">Leave empty to save as draft</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveBroadcast()">Create Broadcast</button>
            </div>
        </div>
    </div>
</div>

<script>
let broadcastModal;
let currentBroadcastId = null;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('broadcastModal');
    if (modalElement) {
        broadcastModal = new bootstrap.Modal(modalElement);
    }
});

function openBroadcastModal() {
    if (!broadcastModal) {
        console.error('Modal not initialized');
        return;
    }
    currentBroadcastId = null;
    document.getElementById('broadcastForm').reset();
    document.querySelector('#broadcastModal .modal-title').textContent = 'New Broadcast';
    document.querySelector('#broadcastModal .btn-primary').textContent = 'Create Broadcast';
    broadcastModal.show();
}

function editBroadcast(id) {
    if (!broadcastModal) {
        console.error('Modal not initialized');
        return;
    }
    
    currentBroadcastId = id;
    const formData = new FormData();
    formData.append('action', 'get');
    formData.append('id', id);
    
    fetch('broadcasts.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('broadcast_name').value = data.broadcast.name;
            document.getElementById('broadcast_message').value = data.broadcast.message;
            if (data.broadcast.scheduled_at) {
                const date = new Date(data.broadcast.scheduled_at);
                const localDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
                document.getElementById('scheduled_at').value = localDate.toISOString().slice(0, 16);
            }
            document.querySelector('#broadcastModal .modal-title').textContent = 'Edit Broadcast';
            document.querySelector('#broadcastModal .btn-primary').textContent = 'Update Broadcast';
            broadcastModal.show();
        } else {
            showToast('Error loading broadcast: ' + data.error, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Error loading broadcast', 'error');
    });
}

function saveBroadcast() {
    const formData = new FormData(document.getElementById('broadcastForm'));
    formData.append('action', currentBroadcastId ? 'update' : 'create');
    if (currentBroadcastId) {
        formData.append('id', currentBroadcastId);
    }
    
    fetch('broadcasts.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast((currentBroadcastId ? 'Broadcast updated' : 'Broadcast created') + ' successfully!', 'success');
            broadcastModal.hide();
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function sendBroadcast(id) {
    if (!confirm('Are you sure you want to send this broadcast now?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('id', id);
    
    fetch('broadcasts.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Broadcast started!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function cancelBroadcast(id) {
    if (!confirm('Are you sure you want to cancel this broadcast?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('id', id);
    
    fetch('broadcasts.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Broadcast cancelled!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function deleteBroadcast(id) {
    if (!confirm('Are you sure you want to delete this broadcast?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('broadcasts.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Broadcast deleted!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function viewBroadcast(id) {
    window.location.href = `broadcast-details.php?id=${id}`;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
