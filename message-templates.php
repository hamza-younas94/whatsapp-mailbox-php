<?php
/**
 * Message Templates Management Page - WhatsApp Template Management
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\MessageTemplate;

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
                    'whatsapp_template_name' => sanitize($_POST['whatsapp_template_name'] ?? ''),
                    'language_code' => sanitize($_POST['language_code'] ?? ''),
                    'content' => sanitize($_POST['content'] ?? '')
                ], [
                    'name' => 'required|min:2|max:150',
                    'whatsapp_template_name' => 'required|min:1|max:100',
                    'language_code' => 'required|max:10',
                    'content' => 'required|min:1'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                // Parse variables from content
                preg_match_all('/\{\{(\d+)\}\}/', $_POST['content'], $matches);
                $variables = [];
                if (!empty($matches[1])) {
                    $variables = array_map('intval', $matches[1]);
                    sort($variables);
                }
                
                $data = [
                    'name' => sanitize($_POST['name']),
                    'whatsapp_template_name' => sanitize($_POST['whatsapp_template_name']),
                    'language_code' => sanitize($_POST['language_code']),
                    'content' => sanitize($_POST['content']),
                    'variables' => $variables,
                    'category' => !empty($_POST['category']) ? sanitize($_POST['category']) : null,
                    'status' => sanitize($_POST['status'] ?? 'pending')
                ];
                
                if ($action === 'create') {
                    $data['created_by'] = $user->id;
                    $template = MessageTemplate::create($data);
                    echo json_encode(['success' => true, 'template' => $template]);
                } else {
                    $template = MessageTemplate::findOrFail($_POST['id']);
                    $template->update($data);
                    echo json_encode(['success' => true, 'template' => $template]);
                }
                break;
            
            case 'delete':
                MessageTemplate::findOrFail($_POST['id'])->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'update_status':
                $template = MessageTemplate::findOrFail($_POST['id']);
                $template->update(['status' => sanitize($_POST['status'])]);
                echo json_encode(['success' => true, 'status' => $template->status]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all templates
$templates = MessageTemplate::with('creator')->orderBy('created_at', 'desc')->get();

$pageTitle = 'Message Templates';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>📝 Message Templates</h1>
            <p>Manage WhatsApp message templates with variables</p>
        </div>
        <button class="btn btn-primary" onclick="openTemplateModal()">
            <i class="fas fa-plus"></i> New Template
        </button>
    </div>

    <!-- Templates Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>WhatsApp Template</th>
                            <th>Language</th>
                            <th>Variables</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Usage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($templates->isEmpty()): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No templates created yet. Click "New Template" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($template->name); ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($template->whatsapp_template_name); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($template->language_code); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $variables = $template->variables ?? [];
                                        if (empty($variables)) {
                                            echo '<small class="text-muted">None</small>';
                                        } else {
                                            echo '<span class="badge bg-secondary">' . count($variables) . ' var' . (count($variables) !== 1 ? 's' : '') . '</span>';
                                            echo '<br><small class="text-muted">' . implode(', ', array_map(function($v) { return '{{' . $v . '}}'; }, $variables)) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($template->category): ?>
                                            <span class="badge bg-light text-dark"><?php echo htmlspecialchars($template->category); ?></span>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $statusColor = $statusColors[$template->status] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $statusColor; ?>" id="status_badge_<?php echo $template->id; ?>">
                                            <?php echo ucfirst($template->status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $template->usage_count ?? 0; ?> times</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editTemplate(<?php echo $template->id; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="previewTemplate(<?php echo $template->id; ?>)" title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="updateTemplateStatus(<?php echo $template->id; ?>, 'approved'); return false;">Approve</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateTemplateStatus(<?php echo $template->id; ?>, 'rejected'); return false;">Reject</a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="updateTemplateStatus(<?php echo $template->id; ?>, 'pending'); return false;">Pending</a></li>
                                                </ul>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(<?php echo $template->id; ?>)" title="Delete">
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

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">New Message Template</h5>
                <button type="button" class="btn-close" onclick="closeTemplateModal()"></button>
            </div>
            <div class="modal-body">
                <form id="templateForm">
                    <input type="hidden" id="template_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="template_name" class="form-label">Template Name *</label>
                            <input type="text" class="form-control" id="template_name" name="name" required>
                            <small class="text-muted">Internal name for this template</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="template_whatsapp_name" class="form-label">WhatsApp Template Name *</label>
                            <input type="text" class="form-control" id="template_whatsapp_name" name="whatsapp_template_name" required>
                            <small class="text-muted">Exact name as in WhatsApp Business Manager</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="template_language" class="form-label">Language Code *</label>
                            <select class="form-select" id="template_language" name="language_code" required>
                                <option value="en">English (en)</option>
                                <option value="en_US">English US (en_US)</option>
                                <option value="en_GB">English UK (en_GB)</option>
                                <option value="es">Spanish (es)</option>
                                <option value="fr">French (fr)</option>
                                <option value="de">German (de)</option>
                                <option value="ar">Arabic (ar)</option>
                                <option value="ur">Urdu (ur)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="template_category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="template_category" name="category" placeholder="e.g., Welcome, Follow-up">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="template_status" class="form-label">Status</label>
                            <select class="form-select" id="template_status" name="status">
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="template_content" class="form-label">Template Content *</label>
                        <textarea class="form-control" id="template_content" name="content" rows="6" required oninput="updateVariablePreview()"></textarea>
                        <small class="text-muted">
                            Use {{1}}, {{2}}, {{3}} etc. for variables. Example: "Hello {{1}}, your order {{2}} is ready!"
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Variable Preview</label>
                        <div id="variablePreview" class="border rounded p-3 bg-light">
                            <p class="text-muted mb-0">Variables will appear here as you type</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeTemplateModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Preview</h5>
                <button type="button" class="btn-close" onclick="closePreviewModal()"></button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
let templatesData = <?php echo json_encode($templates); ?>;
let templateModal = null;
let previewModal = null;

document.addEventListener('DOMContentLoaded', function() {
    templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
    previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    
    // Initialize form validator
    const validator = new FormValidator('templateForm');
    validator.init();
});

function openTemplateModal(templateId = null) {
    document.getElementById('template_id').value = templateId || '';
    document.getElementById('templateModalTitle').innerHTML = templateId ? 'Edit Message Template' : 'New Message Template';
    
    if (templateId) {
        const template = templatesData.find(t => t.id == templateId);
        if (template) {
            document.getElementById('template_name').value = template.name || '';
            document.getElementById('template_whatsapp_name').value = template.whatsapp_template_name || '';
            document.getElementById('template_language').value = template.language_code || 'en';
            document.getElementById('template_category').value = template.category || '';
            document.getElementById('template_status').value = template.status || 'pending';
            document.getElementById('template_content').value = template.content || '';
            updateVariablePreview();
        }
    } else {
        document.getElementById('templateForm').reset();
        document.getElementById('variablePreview').innerHTML = '<p class="text-muted mb-0">Variables will appear here as you type</p>';
    }
    
    templateModal.show();
}

function closeTemplateModal() {
    templateModal.hide();
    document.getElementById('templateForm').reset();
}

function updateVariablePreview() {
    const content = document.getElementById('template_content').value;
    const preview = document.getElementById('variablePreview');
    
    if (!content) {
        preview.innerHTML = '<p class="text-muted mb-0">Variables will appear here as you type</p>';
        return;
    }
    
    // Find all variables
    const regex = /\{\{(\d+)\}\}/g;
    const matches = [...content.matchAll(regex)];
    const variables = [...new Set(matches.map(m => parseInt(m[1])))].sort((a, b) => a - b);
    
    if (variables.length === 0) {
        preview.innerHTML = '<p class="text-muted mb-0">No variables found. Use {{1}}, {{2}}, etc. for variables.</p>';
        return;
    }
    
    let html = '<strong>Found Variables:</strong><br>';
    variables.forEach(v => {
        html += `<span class="badge bg-primary me-1">\${${v}}</span>`;
    });
    html += '<br><small class="text-muted mt-2 d-block">Make sure to provide all variables when sending</small>';
    
    preview.innerHTML = html;
}

function saveTemplate() {
    const validator = new FormValidator('templateForm');
    if (!validator.validate()) {
        return;
    }
    
    const templateId = document.getElementById('template_id').value;
    const formData = new FormData(document.getElementById('templateForm'));
    formData.append('action', templateId ? 'update' : 'create');
    
    fetch('message-templates.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Template saved successfully!', 'success');
            closeTemplateModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to save template', 'error');
            if (data.errors) {
                validator.showErrors(data.errors);
            }
        }
    })
    .catch(err => {
        showToast('Error: ' + err.message, 'error');
    });
}

function editTemplate(id) {
    openTemplateModal(id);
}

function previewTemplate(id) {
    const template = templatesData.find(t => t.id == id);
    if (!template) return;
    
    const preview = document.getElementById('previewContent');
    let html = `
        <div class="mb-3">
            <strong>Template Name:</strong> ${template.name}<br>
            <strong>WhatsApp Name:</strong> <code>${template.whatsapp_template_name}</code><br>
            <strong>Language:</strong> ${template.language_code}
        </div>
        <div class="border rounded p-3 bg-light">
            <strong>Content:</strong><br>
            <pre class="mb-0 mt-2">${template.content}</pre>
        </div>
    `;
    
    const variables = template.variables || [];
    if (variables.length > 0) {
        html += `
            <div class="mt-3">
                <strong>Variables (${variables.length}):</strong><br>
                ${variables.map(v => `<span class="badge bg-primary me-1 mt-2">\${${v}}</span>`).join('')}
            </div>
        `;
    }
    
    preview.innerHTML = html;
    previewModal.show();
}

function closePreviewModal() {
    previewModal.hide();
}

function updateTemplateStatus(id, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('id', id);
    formData.append('status', status);
    
    fetch('message-templates.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Template status updated!', 'success');
            const badge = document.getElementById('status_badge_' + id);
            if (badge) {
                badge.className = 'badge bg-' + (status === 'approved' ? 'success' : (status === 'rejected' ? 'danger' : 'warning'));
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
        } else {
            showToast(data.error || 'Failed to update status', 'error');
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('Are you sure you want to delete this template?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('message-templates.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Template deleted successfully!', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to delete template', 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

