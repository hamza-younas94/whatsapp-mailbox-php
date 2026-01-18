<?php
/**
 * Drip Campaigns Management Page - Multi-step Message Sequences
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\DripCampaign;
use App\Models\DripCampaignStep;
use App\Models\Segment;
use App\Models\Tag;
use Illuminate\Database\Capsule\Manager as Capsule;

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
                    'trigger_conditions' => $_POST['trigger_conditions'] ?? '{}',
                    'steps' => $_POST['steps'] ?? '[]'
                ], [
                    'name' => 'required|min:2|max:150',
                    'trigger_conditions' => 'required',
                    'steps' => 'required'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                // Validate JSON
                $triggerConditions = json_decode($_POST['trigger_conditions'], true);
                $steps = json_decode($_POST['steps'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid JSON format',
                        'errors' => ['steps' => ['Invalid JSON format']]
                    ]);
                    exit;
                }
                
                if (empty($steps) || !is_array($steps)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'At least one step is required',
                        'errors' => ['steps' => ['Please add at least one step']]
                    ]);
                    exit;
                }
                
                Capsule::beginTransaction();
                
                try {
                    $data = [
                        'name' => sanitize($_POST['name']),
                        'description' => !empty($_POST['description']) ? sanitize($_POST['description']) : null,
                        'trigger_conditions' => $triggerConditions,
                        'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
                    ];
                    
                    if ($action === 'create') {
                        $data['created_by'] = $user->id;
                        $campaign = DripCampaign::create($data);
                        $campaignId = $campaign->id;
                    } else {
                        $campaign = DripCampaign::findOrFail($_POST['id']);
                        $campaign->update($data);
                        $campaignId = $campaign->id;
                        
                        // Delete existing steps
                        DripCampaignStep::where('campaign_id', $campaignId)->delete();
                    }
                    
                    // Create steps
                    foreach ($steps as $index => $step) {
                        DripCampaignStep::create([
                            'campaign_id' => $campaignId,
                            'step_number' => $index + 1,
                            'name' => sanitize($step['name'] ?? 'Step ' . ($index + 1)),
                            'delay_minutes' => (int)($step['delay_minutes'] ?? 0),
                            'message_type' => sanitize($step['message_type'] ?? 'text'),
                            'message_content' => sanitize($step['message_content'] ?? ''),
                            'template_id' => !empty($step['template_id']) ? (int)$step['template_id'] : null
                        ]);
                    }
                    
                    Capsule::commit();
                    echo json_encode(['success' => true, 'campaign' => $campaign->fresh(['steps'])]);
                } catch (Exception $e) {
                    Capsule::rollBack();
                    throw $e;
                }
                break;
            
            case 'delete':
                DripCampaign::findOrFail($_POST['id'])->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $campaign = DripCampaign::findOrFail($_POST['id']);
                $campaign->update(['is_active' => !$campaign->is_active]);
                echo json_encode(['success' => true, 'is_active' => $campaign->is_active]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all campaigns with steps
$campaigns = DripCampaign::with(['steps', 'creator'])->orderBy('created_at', 'desc')->get();

// Fetch segments and tags for dropdowns
$segments = Segment::orderBy('name')->get();
$tags = Tag::orderBy('name')->get();

$pageTitle = 'Drip Campaigns';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>💧 Drip Campaigns</h1>
            <p>Create multi-step message sequences with delays</p>
            <?php if ($campaigns->isEmpty()): ?>
                <div class="alert alert-info mt-2 mb-0" style="max-width: 800px;">
                    <i class="fas fa-lightbulb"></i> <strong>Get Started:</strong> Drip campaigns send a series of messages over time.
                    Example: Welcome message → Product overview (1 hour later) → Get started guide (24 hours later).
                    <br><small class="mt-1 d-block"><strong>Or run:</strong> <code>php seed_default_data.php</code> to create sample campaigns</small>
                </div>
            <?php endif; ?>
        </div>
        <button class="btn btn-primary" onclick="openCampaignModal()">
            <i class="fas fa-plus"></i> New Campaign
        </button>
    </div>

    <!-- Campaigns Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Steps</th>
                            <th>Triggers</th>
                            <th>Subscribers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($campaigns->isEmpty()): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No campaigns created yet. Click "New Campaign" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($campaigns as $campaign): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($campaign->name); ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars(substr($campaign->description ?? 'No description', 0, 50)); ?>
                                            <?php if (strlen($campaign->description ?? '') > 50) echo '...'; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $campaign->steps->count(); ?> steps
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $conditions = $campaign->trigger_conditions ?? [];
                                        $triggers = [];
                                        if (isset($conditions['segment_id'])) $triggers[] = 'Segment';
                                        if (isset($conditions['tags']) && !empty($conditions['tags'])) $triggers[] = 'Tags';
                                        if (isset($conditions['stage'])) $triggers[] = 'Stage';
                                        echo !empty($triggers) ? implode(', ', $triggers) : '<small class="text-muted">None</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $campaign->total_subscribers ?? 0; ?></span>
                                        <?php if ($campaign->completed_count > 0): ?>
                                            <br><small class="text-muted"><?php echo $campaign->completed_count; ?> completed</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   <?php echo $campaign->is_active ? 'checked' : ''; ?>
                                                   onchange="toggleCampaign(<?php echo $campaign->id; ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCampaign(<?php echo $campaign->id; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCampaign(<?php echo $campaign->id; ?>)" title="Delete">
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

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="campaignModalTitle">New Drip Campaign</h5>
                <button type="button" class="btn-close" onclick="closeCampaignModal()"></button>
            </div>
            <div class="modal-body">
                <form id="campaignForm">
                    <input type="hidden" id="campaign_id" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="campaign_name" class="form-label">Campaign Name *</label>
                            <input type="text" class="form-control" id="campaign_name" name="name" required>
                            <small class="text-muted">Give your campaign a descriptive name</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="campaign_is_active" class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="campaign_is_active" name="is_active" checked>
                                <label class="form-check-label" for="campaign_is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="campaign_description" class="form-label">Description</label>
                        <textarea class="form-control" id="campaign_description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Trigger Conditions *</label>
                        <div class="border rounded p-3 bg-light">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Segment</label>
                                    <select class="form-select form-select-sm" id="trigger_segment">
                                        <option value="">Any Segment</option>
                                        <?php foreach ($segments as $segment): ?>
                                            <option value="<?php echo $segment->id; ?>"><?php echo htmlspecialchars($segment->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Tags</label>
                                    <select class="form-select form-select-sm" id="trigger_tags" multiple>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo $tag->id; ?>"><?php echo htmlspecialchars($tag->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="form-label small">Stage</label>
                                    <select class="form-select form-select-sm" id="trigger_stage">
                                        <option value="">Any Stage</option>
                                        <option value="NEW">New</option>
                                        <option value="CONTACTED">Contacted</option>
                                        <option value="QUALIFIED">Qualified</option>
                                        <option value="PROPOSAL">Proposal</option>
                                        <option value="NEGOTIATION">Negotiation</option>
                                        <option value="CUSTOMER">Customer</option>
                                        <option value="LOST">Lost</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Campaign Steps *</label>
                        <div id="stepsBuilder" class="border rounded p-3">
                            <p class="text-muted mb-3">No steps added. Click "Add Step" to get started.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStep()">
                            <i class="fas fa-plus"></i> Add Step
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCampaignModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCampaign()">Save Campaign</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
let campaignsData = <?php echo json_encode($campaigns); ?>;
let campaignModal = null;
let stepCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    campaignModal = new bootstrap.Modal(document.getElementById('campaignModal'));
    
    // Initialize form validator
    const validator = new FormValidator('campaignForm');
    validator.init();
});

function openCampaignModal(campaignId = null) {
    document.getElementById('campaign_id').value = campaignId || '';
    document.getElementById('campaignModalTitle').innerHTML = campaignId ? 'Edit Drip Campaign' : 'New Drip Campaign';
    
    if (campaignId) {
        const campaign = campaignsData.find(c => c.id == campaignId);
        if (campaign) {
            document.getElementById('campaign_name').value = campaign.name || '';
            document.getElementById('campaign_description').value = campaign.description || '';
            document.getElementById('campaign_is_active').checked = campaign.is_active !== false;
            
            // Load trigger conditions
            const conditions = campaign.trigger_conditions || {};
            if (conditions.segment_id) document.getElementById('trigger_segment').value = conditions.segment_id;
            if (conditions.tags) {
                const tagSelect = document.getElementById('trigger_tags');
                Array.from(tagSelect.options).forEach(opt => {
                    opt.selected = conditions.tags.includes(parseInt(opt.value));
                });
            }
            if (conditions.stage) document.getElementById('trigger_stage').value = conditions.stage;
            
            // Load steps
            if (campaign.steps && campaign.steps.length > 0) {
                loadSteps(campaign.steps);
            } else {
                document.getElementById('stepsBuilder').innerHTML = '<p class="text-muted mb-3">No steps added. Click "Add Step" to get started.</p>';
                stepCount = 0;
            }
        }
    } else {
        document.getElementById('campaignForm').reset();
        document.getElementById('stepsBuilder').innerHTML = '<p class="text-muted mb-3">No steps added. Click "Add Step" to get started.</p>';
        stepCount = 0;
    }
    
    campaignModal.show();
}

function closeCampaignModal() {
    campaignModal.hide();
    document.getElementById('campaignForm').reset();
}

function addStep() {
    stepCount++;
    const builder = document.getElementById('stepsBuilder');
    
    if (builder.querySelector('.text-muted')) {
        builder.innerHTML = '';
    }
    
    const stepDiv = document.createElement('div');
    stepDiv.className = 'step-item border rounded p-3 mb-3 bg-light';
    stepDiv.id = 'step_' + stepCount;
    
    stepDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start mb-3">
            <strong>Step #${stepCount}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStep(${stepCount})">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label small">Step Name *</label>
                <input type="text" class="form-control form-control-sm" name="step_name_${stepCount}" placeholder="Step ${stepCount}" required>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label small">Delay (minutes) *</label>
                <input type="number" class="form-control form-control-sm" name="step_delay_${stepCount}" min="0" value="0" required>
                <small class="text-muted">Wait time before this step</small>
            </div>
            <div class="col-md-4 mb-2">
                <label class="form-label small">Message Type *</label>
                <select class="form-select form-select-sm" name="step_type_${stepCount}" required onchange="updateStepFields(${stepCount})">
                    <option value="text">Text Message</option>
                    <option value="template">Template Message</option>
                </select>
            </div>
        </div>
        <div id="step_fields_${stepCount}" class="mt-2">
            <div class="mb-2">
                <label class="form-label small">Message *</label>
                <textarea class="form-control form-control-sm" name="step_message_${stepCount}" rows="3" required></textarea>
            </div>
        </div>
    `;
    
    builder.appendChild(stepDiv);
}

function removeStep(id) {
    const stepDiv = document.getElementById('step_' + id);
    if (stepDiv) {
        stepDiv.remove();
    }
    
    const builder = document.getElementById('stepsBuilder');
    if (builder.children.length === 0) {
        builder.innerHTML = '<p class="text-muted mb-3">No steps added. Click "Add Step" to get started.</p>';
        stepCount = 0;
    } else {
        // Renumber steps
        renumberSteps();
    }
}

function renumberSteps() {
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, index) => {
        const id = step.id.replace('step_', '');
        const title = step.querySelector('strong');
        if (title) title.textContent = `Step #${index + 1}`;
    });
}

function updateStepFields(stepId) {
    const messageType = document.querySelector(`[name="step_type_${stepId}"]`).value;
    const fieldsDiv = document.getElementById('step_fields_' + stepId);
    
    let html = '';
    
    if (messageType === 'text') {
        html = `
            <div class="mb-2">
                <label class="form-label small">Message *</label>
                <textarea class="form-control form-control-sm" name="step_message_${stepId}" rows="3" required></textarea>
            </div>
        `;
    } else {
        html = `
            <div class="mb-2">
                <label class="form-label small">Template Name *</label>
                <input type="text" class="form-control form-control-sm" name="step_template_${stepId}" placeholder="template_name" required>
            </div>
            <div class="mb-2">
                <label class="form-label small">Template Parameters (JSON)</label>
                <textarea class="form-control form-control-sm" name="step_params_${stepId}" rows="2" placeholder='["param1", "param2"]'></textarea>
            </div>
        `;
    }
    
    fieldsDiv.innerHTML = html;
}

function loadSteps(steps) {
    if (!steps || !Array.isArray(steps) || steps.length === 0) return;
    
    const builder = document.getElementById('stepsBuilder');
    builder.innerHTML = '';
    stepCount = 0;
    
    steps.forEach((step, index) => {
        stepCount++;
        addStep();
        setTimeout(() => {
            const stepDiv = document.getElementById('step_' + stepCount);
            if (stepDiv) {
                if (step.name) {
                    const nameInput = stepDiv.querySelector(`[name="step_name_${stepCount}"]`);
                    if (nameInput) nameInput.value = step.name;
                }
                if (step.delay_minutes !== undefined) {
                    const delayInput = stepDiv.querySelector(`[name="step_delay_${stepCount}"]`);
                    if (delayInput) delayInput.value = step.delay_minutes;
                }
                if (step.message_type) {
                    const typeSelect = stepDiv.querySelector(`[name="step_type_${stepCount}"]`);
                    if (typeSelect) {
                        typeSelect.value = step.message_type;
                        updateStepFields(stepCount);
                        
                        setTimeout(() => {
                            if (step.message_content) {
                                const messageInput = stepDiv.querySelector(`[name="step_message_${stepCount}"]`);
                                if (messageInput) messageInput.value = step.message_content;
                            }
                            if (step.template_name) {
                                const templateInput = stepDiv.querySelector(`[name="step_template_${stepCount}"]`);
                                if (templateInput) templateInput.value = step.template_name;
                            }
                            if (step.template_params) {
                                const paramsInput = stepDiv.querySelector(`[name="step_params_${stepCount}"]`);
                                if (paramsInput) paramsInput.value = JSON.stringify(step.template_params);
                            }
                        }, 100);
                    }
                }
            }
        }, 50);
    });
}

function getSteps() {
    const steps = [];
    const stepItems = document.querySelectorAll('.step-item');
    
    stepItems.forEach((item, index) => {
        const id = item.id.replace('step_', '');
        const name = document.querySelector(`[name="step_name_${id}"]`)?.value || `Step ${index + 1}`;
        const delayMinutes = parseInt(document.querySelector(`[name="step_delay_${id}"]`)?.value || 0);
        const messageType = document.querySelector(`[name="step_type_${id}"]`)?.value || 'text';
        
        const step = {
            name: name,
            delay_minutes: delayMinutes,
            message_type: messageType
        };
        
        if (messageType === 'text') {
            step.message_content = document.querySelector(`[name="step_message_${id}"]`)?.value || '';
        } else {
            step.template_name = document.querySelector(`[name="step_template_${id}"]`)?.value || '';
            const paramsText = document.querySelector(`[name="step_params_${id}"]`)?.value || '[]';
            try {
                step.template_params = JSON.parse(paramsText);
            } catch (e) {
                step.template_params = [];
            }
        }
        
        steps.push(step);
    });
    
    return steps;
}

function getTriggerConditions() {
    const conditions = {};
    
    const segmentId = document.getElementById('trigger_segment')?.value;
    if (segmentId) conditions.segment_id = parseInt(segmentId);
    
    const tagIds = Array.from(document.getElementById('trigger_tags')?.selectedOptions || []).map(o => parseInt(o.value));
    if (tagIds.length > 0) conditions.tags = tagIds;
    
    const stage = document.getElementById('trigger_stage')?.value;
    if (stage) conditions.stage = stage;
    
    return conditions;
}

function saveCampaign() {
    const validator = new FormValidator('campaignForm');
    if (!validator.validate()) {
        return;
    }
    
    const steps = getSteps();
    if (steps.length === 0) {
        showToast('Please add at least one step', 'error');
        return;
    }
    
    const campaignId = document.getElementById('campaign_id').value;
    const formData = new FormData(document.getElementById('campaignForm'));
    formData.append('action', campaignId ? 'update' : 'create');
    formData.append('trigger_conditions', JSON.stringify(getTriggerConditions()));
    formData.append('steps', JSON.stringify(steps));
    
    fetch('drip-campaigns.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Campaign saved successfully!', 'success');
            closeCampaignModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to save campaign', 'error');
            if (data.errors) {
                validator.showErrors(data.errors);
            }
        }
    })
    .catch(err => {
        showToast('Error: ' + err.message, 'error');
    });
}

function editCampaign(id) {
    openCampaignModal(id);
}

function deleteCampaign(id) {
    if (!confirm('Are you sure you want to delete this campaign? All steps and subscribers will be removed.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('drip-campaigns.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Campaign deleted successfully!', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to delete campaign', 'error');
        }
    });
}

function toggleCampaign(id) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    
    fetch('drip-campaigns.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Campaign status updated', 'success');
        } else {
            showToast(data.error || 'Failed to update campaign', 'error');
            setTimeout(() => location.reload(), 500);
        }
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

