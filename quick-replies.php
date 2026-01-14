<?php
/**
 * Quick Replies Management Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

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
            case 'update':
                // Validate input
                $validation = validate([
                    'shortcut' => sanitize($_POST['shortcut'] ?? ''),
                    'title' => sanitize($_POST['title'] ?? ''),
                    'message' => sanitize($_POST['message'] ?? '')
                ], [
                    'shortcut' => 'required|min:1|max:50',
                    'title' => 'required|min:2|max:100',
                    'message' => 'required|min:1|max:4096'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                $data = [
                    'shortcut' => sanitize($_POST['shortcut']),
                    'title' => sanitize($_POST['title']),
                    'message' => sanitize($_POST['message']),
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
                ];
                
                if ($action === 'create') {
                    $data['created_by'] = $user->id;
                    $reply = QuickReply::create($data);
                    echo json_encode(['success' => true, 'reply' => $reply]);
                } else {
                    $reply = QuickReply::findOrFail($_POST['id']);
                    $reply->update($data);
                    echo json_encode(['success' => true, 'reply' => $reply]);
                }
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
    <div class="page-header">
        <div>
            <h1>⚡ Quick Replies</h1>
            <p>Save time with pre-written message templates</p>
        </div>
        <button class="btn btn-primary" onclick="openReplyModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            New Quick Reply
        </button>
    </div>

    <!-- Fuzzy Matching Info Banner -->
    <div class="alert alert-info d-flex align-items-start mb-4" style="border-left: 4px solid #3b82f6;">
        <i class="fas fa-magic" style="font-size: 24px; margin-right: 15px; margin-top: 3px;"></i>
        <div>
            <h5 class="alert-heading mb-2">
                <i class="fas fa-sparkles"></i> Smart Fuzzy Matching Enabled
            </h5>
            <p class="mb-2">
                Quick replies now trigger automatically when the shortcut keyword appears <strong>anywhere</strong> in the customer's message!
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <div class="badge bg-light text-dark px-3 py-2" style="font-size: 13px;">
                    <strong>Example:</strong> Shortcut <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">/hello</code> triggers on:
                    <br><small class="text-muted">✓ "hello" • "hello there" • "say hello" • "HELLO" • "Hello!"</small>
                </div>
                <div class="badge bg-light text-dark px-3 py-2" style="font-size: 13px;">
                    <strong>Example:</strong> Shortcut <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">/price</code> triggers on:
                    <br><small class="text-muted">✓ "price" • "what's the price" • "show prices" • "pricing info"</small>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-info-circle"></i> Works with or without the <code>/</code> prefix and is case-insensitive
            </small>
        </div>
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
                                <div class="d-flex align-items-center gap-2">
                                    <code class="bg-light px-2 py-1 rounded" style="font-weight: 600;"><?php echo htmlspecialchars($reply->shortcut); ?></code>
                                    <span class="badge bg-info" style="font-size: 10px;" title="Fuzzy matching enabled">
                                        <i class="fas fa-magic"></i> Smart
                                    </span>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                    <i class="fas fa-search"></i> Matches anywhere in message
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($reply->title); ?></td>
                            <td>
                                <div class="message-preview" style="background: #f8f9fa; padding: 8px 12px; border-radius: 8px; border-left: 3px solid #10b981;">
                                    <small style="font-family: -apple-system, system-ui; line-height: 1.4;">
                                        <?php echo nl2br(htmlspecialchars(substr($reply->message, 0, 80)) . (strlen($reply->message) > 80 ? '...' : '')); ?>
                                    </small>
                                </div>
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
                            <label for="reply_shortcut" class="form-label">
                                <i class="fas fa-bolt"></i> Shortcut *
                                <small class="text-muted">(keyword to trigger)</small>
                            </label>
                            <input type="text" class="form-control crm-input" id="reply_shortcut" name="shortcut" placeholder="/hello">
                            <small class="text-muted">
                                <i class="fas fa-magic"></i> <strong>Fuzzy Match:</strong> Triggers when this word appears anywhere in message
                                <br><span class="text-success">✓ Example: "/price" matches "what's the price?", "pricing", "show me price"</span>
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="reply_title" class="form-label">
                                <i class="fas fa-tag"></i> Title *
                            </label>
                            <input type="text" class="form-control crm-input" id="reply_title" name="title" placeholder="Welcome Message">
                            <small class="text-muted">Display name for this quick reply</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reply_message" class="form-label">Message *</label>
                        <textarea class="form-control crm-textarea" id="reply_message" name="message" rows="5" placeholder="Enter your message here..."></textarea>
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
    const modalElement = document.getElementById('replyModal');
    if (modalElement) {
        replyModal = new bootstrap.Modal(modalElement);
    }
});

function openReplyModal() {
    if (!replyModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('replyForm').reset();
    document.getElementById('reply_id').value = '';
    document.getElementById('reply_is_active').checked = true;
    document.getElementById('replyModalTitle').textContent = 'New Quick Reply';
    replyModal.show();
}

function editReply(id) {
    if (!replyModal) {
        console.error('Modal not initialized');
        return;
    }
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

// Initialize reply form validator
let replyValidator;

document.addEventListener('DOMContentLoaded', function() {
    replyValidator = new FormValidator('replyForm', {
        shortcut: ['required', 'min:1', 'max:50'],
        title: ['required', 'min:2', 'max:100'],
        message: ['required', 'min:1', 'max:4096']
    });
});

function saveReply() {
    if (!replyValidator || !replyValidator.validate()) {
        return;
    }
    
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
            // Handle validation errors from backend
            if (data.errors && replyValidator) {
                replyValidator.setErrors(data.errors);
            } else {
                showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save quick reply', 'error');
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
