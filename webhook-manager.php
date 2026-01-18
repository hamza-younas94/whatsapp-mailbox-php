<?php
/**
 * Webhook Manager - Configure and test webhooks
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Webhook;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
            case 'update':
                // Validate input
                $validation = validate([
                    'name' => sanitize($_POST['name'] ?? ''),
                    'url' => sanitize($_POST['url'] ?? ''),
                    'events' => $_POST['events'] ?? '[]'
                ], [
                    'name' => 'required|min:2|max:150',
                    'url' => 'required|url',
                    'events' => 'required'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                // Validate events JSON
                $events = json_decode($_POST['events'], true);
                if (json_last_error() !== JSON_ERROR_NONE || empty($events) || !is_array($events)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'At least one event must be selected',
                        'errors' => ['events' => ['Please select at least one event']]
                    ]);
                    exit;
                }
                
                // Generate secret if not provided
                $secret = !empty($_POST['secret']) ? sanitize($_POST['secret']) : bin2hex(random_bytes(16));
                
                $data = [
                    'name' => sanitize($_POST['name']),
                    'url' => sanitize($_POST['url']),
                    'events' => $events,
                    'secret' => $secret,
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
                ];
                
                if ($action === 'create') {
                    $webhook = Webhook::create($data);
                    echo json_encode(['success' => true, 'webhook' => $webhook]);
                } else {
                    // Don't update secret if not provided
                    if (empty($_POST['secret'])) {
                        unset($data['secret']);
                    }
                    $webhook = Webhook::findOrFail($_POST['id']);
                    $webhook->update($data);
                    echo json_encode(['success' => true, 'webhook' => $webhook]);
                }
                break;
            
            case 'delete':
                Webhook::findOrFail($_POST['id'])->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $webhook = Webhook::findOrFail($_POST['id']);
                $webhook->update(['is_active' => !$webhook->is_active]);
                echo json_encode(['success' => true, 'is_active' => $webhook->is_active]);
                break;
            
            case 'test':
                $webhook = Webhook::findOrFail($_POST['id']);
                $testEvent = sanitize($_POST['test_event'] ?? 'message.received');
                $testPayload = !empty($_POST['test_payload']) ? json_decode($_POST['test_payload'], true) : [
                    'event' => $testEvent,
                    'timestamp' => date('c'),
                    'data' => [
                        'message_id' => 'test_' . time(),
                        'from' => '1234567890',
                        'body' => 'Test message'
                    ]
                ];
                
                $result = $webhook->trigger($testEvent, $testPayload);
                
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Webhook triggered successfully' : 'Webhook trigger failed'
                ]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all webhooks
$webhooks = Webhook::orderBy('created_at', 'desc')->get();

// Available events
$availableEvents = [
    'message.received' => 'Message Received',
    'message.sent' => 'Message Sent',
    'message.delivered' => 'Message Delivered',
    'message.read' => 'Message Read',
    'message.failed' => 'Message Failed',
    'contact.created' => 'Contact Created',
    'contact.updated' => 'Contact Updated',
    'stage.changed' => 'Stage Changed',
    'tag.added' => 'Tag Added',
    'tag.removed' => 'Tag Removed'
];

$pageTitle = 'Webhook Manager';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>🔗 Webhook Manager</h1>
            <p>Configure <strong>outgoing</strong> webhooks to send notifications to external systems</p>
            <div class="alert alert-info mt-2 mb-0" style="max-width: 800px;">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> This page manages <strong>outgoing</strong> webhooks (your system → external URLs).
                For <strong>incoming</strong> WhatsApp webhooks (Meta → your system), see <code>webhook.php</code>.
                Outgoing webhooks are automatically triggered by the system (e.g., when messages are received).
            </div>
        </div>
        <button class="btn btn-primary" onclick="openWebhookModal()">
            <i class="fas fa-plus"></i> New Webhook
        </button>
    </div>
    
    <?php if ($webhooks->isEmpty()): ?>
        <div class="alert alert-warning">
            <h5><i class="fas fa-database"></i> No webhooks configured</h5>
            <p class="mb-2">You don't have any webhooks yet. Create one to start receiving notifications about system events.</p>
            <p class="mb-2"><strong>Quick Start:</strong></p>
            <ol class="mb-0">
                <li>Click "New Webhook" above</li>
                <li>Enter your webhook URL (e.g., https://your-domain.com/webhook-handler)</li>
                <li>Select events to listen for (e.g., message.received, contact.created)</li>
                <li>Activate the webhook</li>
            </ol>
            <p class="mt-3 mb-0">
                <small><strong>Or run:</strong> <code>php seed_default_data.php</code> to create sample webhooks</small>
            </p>
        </div>
    <?php endif; ?>

    <!-- Webhooks Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>URL</th>
                            <th>Events</th>
                            <th>Status</th>
                            <th>Statistics</th>
                            <th>Last Triggered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($webhooks->isEmpty()): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No webhooks configured yet. Click "New Webhook" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($webhooks as $webhook): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($webhook->name); ?></strong>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px;"><?php echo htmlspecialchars(substr($webhook->url, 0, 50)); ?><?php echo strlen($webhook->url) > 50 ? '...' : ''; ?></code>
                                    </td>
                                    <td>
                                        <?php
                                        $events = $webhook->events ?? [];
                                        if (empty($events)) {
                                            echo '<small class="text-muted">None</small>';
                                        } else {
                                            echo '<span class="badge bg-info">' . count($events) . ' event' . (count($events) !== 1 ? 's' : '') . '</span>';
                                            echo '<br><small class="text-muted" title="' . implode(', ', $events) . '">' . implode(', ', array_slice($events, 0, 2)) . (count($events) > 2 ? '...' : '') . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   <?php echo $webhook->is_active ? 'checked' : ''; ?>
                                                   onchange="toggleWebhook(<?php echo $webhook->id; ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="badge bg-success">✓ <?php echo $webhook->success_count ?? 0; ?></span>
                                            <span class="badge bg-danger">✗ <?php echo $webhook->failure_count ?? 0; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($webhook->last_triggered_at): ?>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($webhook->last_triggered_at)); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editWebhook(<?php echo $webhook->id; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="testWebhook(<?php echo $webhook->id; ?>)" title="Test">
                                                <i class="fas fa-vial"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteWebhook(<?php echo $webhook->id; ?>)" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Webhook Modal -->
<div class="modal fade" id="webhookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="webhookModalTitle">New Webhook</h5>
                <button type="button" class="btn-close" onclick="closeWebhookModal()"></button>
            </div>
            <div class="modal-body">
                <form id="webhookForm">
                    <input type="hidden" id="webhook_id" name="id">
                    
                    <div class="mb-3">
                        <label for="webhook_name" class="form-label">Webhook Name *</label>
                        <input type="text" class="form-control" id="webhook_name" name="name" required>
                        <small class="text-muted">Give your webhook a descriptive name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="webhook_url" class="form-label">Webhook URL *</label>
                        <input type="url" class="form-control" id="webhook_url" name="url" required placeholder="https://your-domain.com/webhook">
                        <small class="text-muted">The URL to send webhook notifications to</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Events *</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="row">
                                <?php foreach ($availableEvents as $event => $label): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="event[]" value="<?php echo $event; ?>" id="event_<?php echo $event; ?>">
                                            <label class="form-check-label" for="event_<?php echo $event; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <small class="text-muted">Select at least one event to listen for</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="webhook_secret" class="form-label">Secret Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="webhook_secret" name="secret" placeholder="Leave empty to auto-generate">
                            <button type="button" class="btn btn-outline-secondary" onclick="generateSecret()">
                                <i class="fas fa-sync"></i> Generate
                            </button>
                        </div>
                        <small class="text-muted">Secret key for HMAC signature verification (auto-generated if empty)</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="webhook_is_active" name="is_active" checked>
                            <label class="form-check-label" for="webhook_is_active">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeWebhookModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveWebhook()">Save Webhook</button>
            </div>
        </div>
    </div>
</div>

<!-- Test Webhook Modal -->
<div class="modal fade" id="testWebhookModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Webhook</h5>
                <button type="button" class="btn-close" onclick="closeTestWebhookModal()"></button>
            </div>
            <div class="modal-body">
                <form id="testWebhookForm">
                    <input type="hidden" id="test_webhook_id" name="webhook_id">
                    
                    <div class="mb-3">
                        <label for="test_event" class="form-label">Test Event</label>
                        <select class="form-select" id="test_event" name="test_event">
                            <?php foreach ($availableEvents as $event => $label): ?>
                                <option value="<?php echo $event; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="test_payload" class="form-label">Test Payload (JSON)</label>
                        <textarea class="form-control" id="test_payload" name="test_payload" rows="8" style="font-family: monospace; font-size: 12px;">{
  "event": "message.received",
  "timestamp": "<?php echo date('c'); ?>",
  "data": {
    "message_id": "test_<?php echo time(); ?>",
    "from": "1234567890",
    "body": "Test message",
    "contact": {
      "id": 1,
      "name": "Test Contact"
    }
  }
}</textarea>
                        <small class="text-muted">Customize the test payload or leave default</small>
                    </div>
                    
                    <div id="testResult" class="alert" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTestWebhookModal()">Close</button>
                <button type="button" class="btn btn-primary" onclick="executeTest()">
                    <i class="fas fa-paper-plane"></i> Send Test
                </button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
let webhooksData = <?php echo json_encode($webhooks); ?>;
let webhookModal = null;
let testWebhookModal = null;

document.addEventListener('DOMContentLoaded', function() {
    webhookModal = new bootstrap.Modal(document.getElementById('webhookModal'));
    testWebhookModal = new bootstrap.Modal(document.getElementById('testWebhookModal'));
    
    // Initialize form validator
    const validator = new FormValidator('webhookForm');
    validator.init();
});

function openWebhookModal(webhookId = null) {
    document.getElementById('webhook_id').value = webhookId || '';
    document.getElementById('webhookModalTitle').innerHTML = webhookId ? 'Edit Webhook' : 'New Webhook';
    
    if (webhookId) {
        const webhook = webhooksData.find(w => w.id == webhookId);
        if (webhook) {
            document.getElementById('webhook_name').value = webhook.name || '';
            document.getElementById('webhook_url').value = webhook.url || '';
            document.getElementById('webhook_secret').value = webhook.secret || '';
            document.getElementById('webhook_is_active').checked = webhook.is_active !== false;
            
            // Load events
            const events = webhook.events || [];
            events.forEach(event => {
                const checkbox = document.getElementById('event_' + event);
                if (checkbox) checkbox.checked = true;
            });
        }
    } else {
        document.getElementById('webhookForm').reset();
        // Uncheck all events
        document.querySelectorAll('input[name="event[]"]').forEach(cb => cb.checked = false);
    }
    
    webhookModal.show();
}

function closeWebhookModal() {
    webhookModal.hide();
    document.getElementById('webhookForm').reset();
}

function generateSecret() {
    const secret = Array.from(crypto.getRandomValues(new Uint8Array(16)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    document.getElementById('webhook_secret').value = secret;
}

function saveWebhook() {
    const validator = new FormValidator('webhookForm');
    if (!validator.validate()) {
        return;
    }
    
    // Get selected events
    const selectedEvents = Array.from(document.querySelectorAll('input[name="event[]"]:checked'))
        .map(cb => cb.value);
    
    if (selectedEvents.length === 0) {
        showToast('Please select at least one event', 'error');
        return;
    }
    
    const webhookId = document.getElementById('webhook_id').value;
    const formData = new FormData(document.getElementById('webhookForm'));
    formData.append('action', webhookId ? 'update' : 'create');
    formData.append('events', JSON.stringify(selectedEvents));
    
    // Don't send secret if empty (will auto-generate)
    if (!formData.get('secret')) {
        formData.delete('secret');
    }
    
    fetch('webhook-manager.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Webhook saved successfully!', 'success');
            closeWebhookModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to save webhook', 'error');
            if (data.errors) {
                validator.showErrors(data.errors);
            }
        }
    })
    .catch(err => {
        showToast('Error: ' + err.message, 'error');
    });
}

function editWebhook(id) {
    openWebhookModal(id);
}

function deleteWebhook(id) {
    if (!confirm('Are you sure you want to delete this webhook?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('webhook-manager.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Webhook deleted successfully!', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to delete webhook', 'error');
        }
    });
}

function toggleWebhook(id) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    
    fetch('webhook-manager.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Webhook status updated', 'success');
        } else {
            showToast(data.error || 'Failed to update webhook', 'error');
            setTimeout(() => location.reload(), 500);
        }
    });
}

function testWebhook(id) {
    document.getElementById('test_webhook_id').value = id;
    testWebhookModal.show();
}

function closeTestWebhookModal() {
    testWebhookModal.hide();
    document.getElementById('testResult').style.display = 'none';
}

function executeTest() {
    const webhookId = document.getElementById('test_webhook_id').value;
    const testEvent = document.getElementById('test_event').value;
    const testPayload = document.getElementById('test_payload').value;
    
    // Validate JSON
    try {
        JSON.parse(testPayload);
    } catch (e) {
        showToast('Invalid JSON in test payload', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'test');
    formData.append('id', webhookId);
    formData.append('test_event', testEvent);
    formData.append('test_payload', testPayload);
    
    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    resultDiv.className = 'alert alert-info';
    resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending test webhook...';
    
    fetch('webhook-manager.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultDiv.className = 'alert alert-success';
            resultDiv.innerHTML = '<i class="fas fa-check-circle"></i> Test webhook sent successfully! Check your endpoint for the payload.';
        } else {
            resultDiv.className = 'alert alert-danger';
            resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> ' + (data.error || 'Test webhook failed');
        }
    })
    .catch(err => {
        resultDiv.className = 'alert alert-danger';
        resultDiv.innerHTML = '<i class="fas fa-times-circle"></i> Error: ' + err.message;
    });
}
</script>

<script>
// Toast notification function (if not already defined)
if (typeof showToast === 'undefined') {
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

