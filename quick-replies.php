<?php
/**
 * Quick Replies Management Page
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

use App\Models\QuickReply;

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
                $reply = QuickReply::create([
                    'shortcut' => $_POST['shortcut'],
                    'title' => $_POST['title'],
                    'message' => $_POST['message'],
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1',
                    'created_by' => $user->id
                ]);
                echo json_encode(['success' => true, 'reply' => $reply]);
                break;
            
            case 'update':
                $reply = QuickReply::findOrFail($_POST['id']);
                $reply->update([
                    'shortcut' => $_POST['shortcut'],
                    'title' => $_POST['title'],
                    'message' => $_POST['message'],
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
                ]);
                echo json_encode(['success' => true, 'reply' => $reply]);
                break;
            
            case 'delete':
                $reply = QuickReply::findOrFail($_POST['id']);
                $reply->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $reply = QuickReply::findOrFail($_POST['id']);
                $reply->update(['is_active' => !$reply->is_active]);
                echo json_encode(['success' => true, 'is_active' => $reply->is_active]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all quick replies
$replies = QuickReply::with('creator')->orderBy('usage_count', 'desc')->get();

// Render page
$pageTitle = 'Quick Replies';
$pageDescription = 'Manage canned responses for faster messaging';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">⚡ Quick Replies</h1>
            <p class="text-muted">Save time with pre-written message templates</p>
        </div>
        <button class="btn btn-primary" onclick="openReplyModal()">
            <i class="fas fa-plus"></i> New Quick Reply
        </button>
    </div>

    <!-- Quick Replies Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Shortcut</th>
                            <th>Title</th>
                            <th>Message Preview</th>
                            <th>Usage Count</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($replies as $reply): ?>
                        <tr>
                            <td>
                                <code class="bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($reply->shortcut); ?></code>
                            </td>
                            <td><?php echo htmlspecialchars($reply->title); ?></td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars(substr($reply->message, 0, 50)) . (strlen($reply->message) > 50 ? '...' : ''); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $reply->usage_count; ?> times</span>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           <?php echo $reply->is_active ? 'checked' : ''; ?>
                                           onchange="toggleReply(<?php echo $reply->id; ?>)">
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($reply->creator->username ?? 'Admin'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editReply(<?php echo $reply->id; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteReply(<?php echo $reply->id; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="copyMessage(<?php echo $reply->id; ?>)">
                                    <i class="fas fa-copy"></i>
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

<!-- Quick Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyModalTitle">New Quick Reply</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="replyForm">
                    <input type="hidden" id="reply_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reply_shortcut" class="form-label">Shortcut <small class="text-muted">(e.g., /hello)</small></label>
                            <input type="text" class="form-control" id="reply_shortcut" name="shortcut" required placeholder="/hello">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reply_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="reply_title" name="title" required placeholder="Welcome Message">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">Message</label>
                        <textarea class="form-control" id="reply_message" name="message" rows="5" required placeholder="Enter your message here..."></textarea>
                        <small class="text-muted">Tip: Use line breaks for better formatting</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="reply_is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="reply_is_active">
                            Active
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveReply()">Save Quick Reply</button>
            </div>
        </div>
    </div>
</div>

<script>
let replyModal;
const repliesData = <?php echo json_encode($replies); ?>;

document.addEventListener('DOMContentLoaded', function() {
    replyModal = new bootstrap.Modal(document.getElementById('replyModal'));
});

function openReplyModal() {
    document.getElementById('replyForm').reset();
    document.getElementById('reply_id').value = '';
    document.getElementById('reply_is_active').checked = true;
    document.getElementById('replyModalTitle').textContent = 'New Quick Reply';
    replyModal.show();
}

function editReply(id) {
    const reply = repliesData.find(r => r.id === id);
    if (reply) {
        document.getElementById('reply_id').value = reply.id;
        document.getElementById('reply_shortcut').value = reply.shortcut;
        document.getElementById('reply_title').value = reply.title;
        document.getElementById('reply_message').value = reply.message;
        document.getElementById('reply_is_active').checked = reply.is_active;
        document.getElementById('replyModalTitle').textContent = 'Edit Quick Reply';
        replyModal.show();
    }
}

function saveReply() {
    const formData = new FormData(document.getElementById('replyForm'));
    const replyId = document.getElementById('reply_id').value;
    
    formData.append('action', replyId ? 'update' : 'create');
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Quick reply saved successfully!', 'success');
            replyModal.hide();
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function deleteReply(id) {
    if (!confirm('Are you sure you want to delete this quick reply?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Quick reply deleted successfully!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function toggleReply(id) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Status updated!', 'success');
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function copyMessage(id) {
    const reply = repliesData.find(r => r.id === id);
    if (reply) {
        navigator.clipboard.writeText(reply.message).then(() => {
            showToast('Message copied to clipboard!', 'success');
        });
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
