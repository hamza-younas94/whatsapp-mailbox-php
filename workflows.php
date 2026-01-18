<?php
/**
 * Workflows Management Page - Automation Rules
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Workflow;
use App\Models\Segment;
use App\Models\Tag;
use App\Models\Contact;

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
                    'trigger_type' => sanitize($_POST['trigger_type'] ?? ''),
                    'trigger_conditions' => $_POST['trigger_conditions'] ?? '{}',
                    'actions' => $_POST['actions'] ?? '[]'
                ], [
                    'name' => 'required|min:2|max:150',
                    'trigger_type' => 'required',
                    'trigger_conditions' => 'required',
                    'actions' => 'required'
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
                $actions = json_decode($_POST['actions'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid JSON format',
                        'errors' => ['trigger_conditions' => ['Invalid JSON format']]
                    ]);
                    exit;
                }
                
                if (empty($actions) || !is_array($actions)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'At least one action is required',
                        'errors' => ['actions' => ['Please add at least one action']]
                    ]);
                    exit;
                }
                
                $data = [
                    'name' => sanitize($_POST['name']),
                    'description' => !empty($_POST['description']) ? sanitize($_POST['description']) : null,
                    'trigger_type' => sanitize($_POST['trigger_type']),
                    'trigger_conditions' => $triggerConditions,
                    'actions' => $actions,
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
                ];
                
                if ($action === 'create') {
                    $data['created_by'] = $user->id;
                    $workflow = Workflow::create($data);
                    echo json_encode(['success' => true, 'workflow' => $workflow]);
                } else {
                    $workflow = Workflow::findOrFail($_POST['id']);
                    $workflow->update($data);
                    echo json_encode(['success' => true, 'workflow' => $workflow]);
                }
                break;
            
            case 'delete':
                Workflow::findOrFail($_POST['id'])->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $workflow = Workflow::findOrFail($_POST['id']);
                $workflow->update(['is_active' => !$workflow->is_active]);
                echo json_encode(['success' => true, 'is_active' => $workflow->is_active]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all workflows
$workflows = Workflow::with('creator')->orderBy('created_at', 'desc')->get();

// Fetch segments and tags for dropdowns
$segments = Segment::orderBy('name')->get();
$tags = Tag::orderBy('name')->get();

$pageTitle = 'Workflows';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>🔄 Workflows</h1>
            <p>Automate actions based on triggers and conditions</p>
            <?php if ($workflows->isEmpty()): ?>
                <div class="alert alert-info mt-2 mb-0" style="max-width: 800px;">
                    <i class="fas fa-lightbulb"></i> <strong>Get Started:</strong> Workflows automatically perform actions when specific triggers occur.
                    Example: Send a welcome message when a new message is received.
                    <br><small class="mt-1 d-block"><strong>Or run:</strong> <code>php seed_default_data.php</code> to create sample workflows</small>
                </div>
            <?php endif; ?>
        </div>
        <button class="btn btn-primary" onclick="openWorkflowModal()">
            <i class="fas fa-plus"></i> New Workflow
        </button>
    </div>

    <!-- Workflows Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Trigger</th>
                            <th>Conditions</th>
                            <th>Actions</th>
                            <th>Executions</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($workflows->isEmpty()): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No workflows created yet. Click "New Workflow" to get started.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($workflows as $workflow): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($workflow->name); ?></strong>
                                        <?php if ($workflow->description): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($workflow->description); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php 
                                            $triggerTypes = [
                                                'new_message' => 'New Message',
                                                'stage_change' => 'Stage Change',
                                                'tag_added' => 'Tag Added',
                                                'tag_removed' => 'Tag Removed',
                                                'time_based' => 'Time Based',
                                                'lead_score_change' => 'Lead Score'
                                            ];
                                            echo $triggerTypes[$workflow->trigger_type] ?? ucfirst($workflow->trigger_type);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            $conditions = $workflow->trigger_conditions ?? [];
                                            echo count($conditions) . ' condition' . (count($conditions) !== 1 ? 's' : '');
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            $actions = $workflow->actions ?? [];
                                            echo count($actions) . ' action' . (count($actions) !== 1 ? 's' : '');
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo $workflow->execution_count ?? 0; ?>
                                        </span>
                                        <?php if ($workflow->last_executed_at): ?>
                                            <br><small class="text-muted"><?php echo date('M d, Y', strtotime($workflow->last_executed_at)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   <?php echo $workflow->is_active ? 'checked' : ''; ?>
                                                   onchange="toggleWorkflow(<?php echo $workflow->id; ?>)">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editWorkflow(<?php echo $workflow->id; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteWorkflow(<?php echo $workflow->id; ?>)" title="Delete">
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

<!-- Workflow Modal -->
<div class="modal fade" id="workflowModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="workflowModalTitle">New Workflow</h5>
                <button type="button" class="btn-close" onclick="closeWorkflowModal()"></button>
            </div>
            <div class="modal-body">
                <form id="workflowForm">
                    <input type="hidden" id="workflow_id" name="id">
                    
                    <div class="mb-3">
                        <label for="workflow_name" class="form-label">Name *</label>
                        <input type="text" class="form-control" id="workflow_name" name="name" required>
                        <small class="text-muted">Give your workflow a descriptive name</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="workflow_description" class="form-label">Description</label>
                        <textarea class="form-control" id="workflow_description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="workflow_trigger_type" class="form-label">Trigger Type *</label>
                        <select class="form-select" id="workflow_trigger_type" name="trigger_type" required onchange="updateTriggerFields()">
                            <option value="">Select trigger...</option>
                            <option value="new_message">New Message Received</option>
                            <option value="stage_change">Contact Stage Changed</option>
                            <option value="tag_added">Tag Added to Contact</option>
                            <option value="tag_removed">Tag Removed from Contact</option>
                            <option value="time_based">Time Based (Scheduled)</option>
                            <option value="lead_score_change">Lead Score Changed</option>
                        </select>
                    </div>
                    
                    <div id="triggerConditions" class="mb-3" style="display: none;">
                        <label class="form-label">Trigger Conditions</label>
                        <div id="triggerConditionsBuilder" class="border rounded p-3 bg-light">
                            <p class="text-muted mb-0">Configure conditions based on selected trigger type</p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Actions *</label>
                        <div id="actionsBuilder" class="border rounded p-3">
                            <p class="text-muted mb-3">No actions added. Click "Add Action" to get started.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addAction()">
                            <i class="fas fa-plus"></i> Add Action
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="workflow_is_active" name="is_active" checked>
                            <label class="form-check-label" for="workflow_is_active">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeWorkflowModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveWorkflow()">Save Workflow</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
let workflowsData = <?php echo json_encode($workflows); ?>;
let workflowModal = null;
let actionCount = 0;

document.addEventListener('DOMContentLoaded', function() {
    workflowModal = new bootstrap.Modal(document.getElementById('workflowModal'));
    
    // Initialize form validator
    const validator = new FormValidator('workflowForm');
    validator.init();
});

function openWorkflowModal(workflowId = null) {
    document.getElementById('workflow_id').value = workflowId || '';
    document.getElementById('workflowModalTitle').innerHTML = workflowId ? 'Edit Workflow' : 'New Workflow';
    
    if (workflowId) {
        const workflow = workflowsData.find(w => w.id == workflowId);
        if (workflow) {
            document.getElementById('workflow_name').value = workflow.name || '';
            document.getElementById('workflow_description').value = workflow.description || '';
            document.getElementById('workflow_trigger_type').value = workflow.trigger_type || '';
            document.getElementById('workflow_is_active').checked = workflow.is_active !== false;
            
            updateTriggerFields();
            loadTriggerConditions(workflow.trigger_conditions);
            loadActions(workflow.actions);
        }
    } else {
        document.getElementById('workflowForm').reset();
        document.getElementById('triggerConditionsBuilder').innerHTML = '<p class="text-muted mb-0">Configure conditions based on selected trigger type</p>';
        document.getElementById('actionsBuilder').innerHTML = '<p class="text-muted mb-3">No actions added. Click "Add Action" to get started.</p>';
        actionCount = 0;
    }
    
    workflowModal.show();
}

function closeWorkflowModal() {
    workflowModal.hide();
    document.getElementById('workflowForm').reset();
}

function updateTriggerFields() {
    const triggerType = document.getElementById('workflow_trigger_type').value;
    const conditionsDiv = document.getElementById('triggerConditions');
    const builder = document.getElementById('triggerConditionsBuilder');
    
    if (!triggerType) {
        conditionsDiv.style.display = 'none';
        return;
    }
    
    conditionsDiv.style.display = 'block';
    
    let html = '';
    
    switch (triggerType) {
        case 'new_message':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Message Contains Keyword</label>
                    <input type="text" class="form-control form-control-sm" id="trigger_keyword" placeholder="Enter keyword">
                </div>
                <div class="mb-2">
                    <label class="form-label small">From Contact Tags</label>
                    <select class="form-select form-select-sm" id="trigger_tags" multiple>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag->id; ?>"><?php echo htmlspecialchars($tag->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            `;
            break;
        case 'stage_change':
            html = `
                <div class="mb-2">
                    <label class="form-label small">From Stage</label>
                    <select class="form-select form-select-sm" id="trigger_from_stage">
                        <option value="">Any</option>
                        <option value="NEW">New</option>
                        <option value="CONTACTED">Contacted</option>
                        <option value="QUALIFIED">Qualified</option>
                        <option value="PROPOSAL">Proposal</option>
                        <option value="NEGOTIATION">Negotiation</option>
                        <option value="CUSTOMER">Customer</option>
                        <option value="LOST">Lost</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">To Stage</label>
                    <select class="form-select form-select-sm" id="trigger_to_stage">
                        <option value="">Any</option>
                        <option value="NEW">New</option>
                        <option value="CONTACTED">Contacted</option>
                        <option value="QUALIFIED">Qualified</option>
                        <option value="PROPOSAL">Proposal</option>
                        <option value="NEGOTIATION">Negotiation</option>
                        <option value="CUSTOMER">Customer</option>
                        <option value="LOST">Lost</option>
                    </select>
                </div>
            `;
            break;
        case 'tag_added':
        case 'tag_removed':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Tag</label>
                    <select class="form-select form-select-sm" id="trigger_tag_id">
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag->id; ?>"><?php echo htmlspecialchars($tag->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            `;
            break;
        case 'lead_score_change':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Score Reaches</label>
                    <input type="number" class="form-control form-control-sm" id="trigger_score" min="0" max="100" placeholder="0-100">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Direction</label>
                    <select class="form-select form-select-sm" id="trigger_direction">
                        <option value="above">Above</option>
                        <option value="below">Below</option>
                    </select>
                </div>
            `;
            break;
        case 'time_based':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Schedule</label>
                    <input type="datetime-local" class="form-control form-select-sm" id="trigger_schedule">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Repeat</label>
                    <select class="form-select form-select-sm" id="trigger_repeat">
                        <option value="once">Once</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
            `;
            break;
    }
    
    builder.innerHTML = html;
}

function loadTriggerConditions(conditions) {
    if (!conditions || Object.keys(conditions).length === 0) return;
    
    const triggerType = document.getElementById('workflow_trigger_type').value;
    if (!triggerType) return;
    
    updateTriggerFields();
    
    // Populate fields based on conditions
    setTimeout(() => {
        if (conditions.keyword && document.getElementById('trigger_keyword')) {
            document.getElementById('trigger_keyword').value = conditions.keyword;
        }
        if (conditions.tag_id && document.getElementById('trigger_tag_id')) {
            document.getElementById('trigger_tag_id').value = conditions.tag_id;
        }
        if (conditions.from_stage && document.getElementById('trigger_from_stage')) {
            document.getElementById('trigger_from_stage').value = conditions.from_stage;
        }
        if (conditions.to_stage && document.getElementById('trigger_to_stage')) {
            document.getElementById('trigger_to_stage').value = conditions.to_stage;
        }
        if (conditions.score && document.getElementById('trigger_score')) {
            document.getElementById('trigger_score').value = conditions.score;
        }
        if (conditions.direction && document.getElementById('trigger_direction')) {
            document.getElementById('trigger_direction').value = conditions.direction;
        }
    }, 100);
}

function getTriggerConditions() {
    const triggerType = document.getElementById('workflow_trigger_type').value;
    const conditions = {};
    
    switch (triggerType) {
        case 'new_message':
            const keyword = document.getElementById('trigger_keyword')?.value;
            if (keyword) conditions.keyword = keyword;
            const tagIds = Array.from(document.getElementById('trigger_tags')?.selectedOptions || []).map(o => parseInt(o.value));
            if (tagIds.length > 0) conditions.tag_ids = tagIds;
            break;
        case 'stage_change':
            const fromStage = document.getElementById('trigger_from_stage')?.value;
            const toStage = document.getElementById('trigger_to_stage')?.value;
            if (fromStage) conditions.from_stage = fromStage;
            if (toStage) conditions.to_stage = toStage;
            break;
        case 'tag_added':
        case 'tag_removed':
            const tagId = document.getElementById('trigger_tag_id')?.value;
            if (tagId) conditions.tag_id = parseInt(tagId);
            break;
        case 'lead_score_change':
            const score = document.getElementById('trigger_score')?.value;
            const direction = document.getElementById('trigger_direction')?.value;
            if (score) conditions.score = parseInt(score);
            if (direction) conditions.direction = direction;
            break;
        case 'time_based':
            const schedule = document.getElementById('trigger_schedule')?.value;
            const repeat = document.getElementById('trigger_repeat')?.value;
            if (schedule) conditions.schedule = schedule;
            if (repeat) conditions.repeat = repeat;
            break;
    }
    
    return conditions;
}

function addAction() {
    actionCount++;
    const builder = document.getElementById('actionsBuilder');
    
    if (builder.querySelector('.text-muted')) {
        builder.innerHTML = '';
    }
    
    const actionDiv = document.createElement('div');
    actionDiv.className = 'action-item border rounded p-3 mb-3';
    actionDiv.id = 'action_' + actionCount;
    
    actionDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-start mb-2">
            <strong>Action #${actionCount}</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAction(${actionCount})">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-2">
            <label class="form-label small">Action Type</label>
            <select class="form-select form-select-sm" name="action_type_${actionCount}" onchange="updateActionFields(${actionCount})">
                <option value="">Select action...</option>
                <option value="send_message">Send Message</option>
                <option value="send_template">Send Template</option>
                <option value="add_tag">Add Tag</option>
                <option value="remove_tag">Remove Tag</option>
                <option value="change_stage">Change Stage</option>
                <option value="create_note">Create Note</option>
                <option value="assign_contact">Assign Contact</option>
            </select>
        </div>
        <div id="action_fields_${actionCount}"></div>
    `;
    
    builder.appendChild(actionDiv);
}

function removeAction(id) {
    const actionDiv = document.getElementById('action_' + id);
    if (actionDiv) {
        actionDiv.remove();
    }
    
    const builder = document.getElementById('actionsBuilder');
    if (builder.children.length === 0) {
        builder.innerHTML = '<p class="text-muted mb-3">No actions added. Click "Add Action" to get started.</p>';
        actionCount = 0;
    }
}

function updateActionFields(actionId) {
    const actionType = document.querySelector(`[name="action_type_${actionId}"]`).value;
    const fieldsDiv = document.getElementById('action_fields_' + actionId);
    
    let html = '';
    
    switch (actionType) {
        case 'send_message':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Message</label>
                    <textarea class="form-control form-control-sm" name="action_message_${actionId}" rows="3"></textarea>
                </div>
            `;
            break;
        case 'send_template':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Template Name</label>
                    <input type="text" class="form-control form-control-sm" name="action_template_${actionId}" placeholder="template_name">
                </div>
                <div class="mb-2">
                    <label class="form-label small">Parameters (JSON)</label>
                    <textarea class="form-control form-control-sm" name="action_params_${actionId}" rows="2" placeholder='["param1", "param2"]'></textarea>
                </div>
            `;
            break;
        case 'add_tag':
        case 'remove_tag':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Tag</label>
                    <select class="form-select form-select-sm" name="action_tag_${actionId}">
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag->id; ?>"><?php echo htmlspecialchars($tag->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            `;
            break;
        case 'change_stage':
            html = `
                <div class="mb-2">
                    <label class="form-label small">New Stage</label>
                    <select class="form-select form-select-sm" name="action_stage_${actionId}">
                        <option value="NEW">New</option>
                        <option value="CONTACTED">Contacted</option>
                        <option value="QUALIFIED">Qualified</option>
                        <option value="PROPOSAL">Proposal</option>
                        <option value="NEGOTIATION">Negotiation</option>
                        <option value="CUSTOMER">Customer</option>
                        <option value="LOST">Lost</option>
                    </select>
                </div>
            `;
            break;
        case 'create_note':
            html = `
                <div class="mb-2">
                    <label class="form-label small">Note</label>
                    <textarea class="form-control form-control-sm" name="action_note_${actionId}" rows="2"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Note Type</label>
                    <select class="form-select form-select-sm" name="action_note_type_${actionId}">
                        <option value="general">General</option>
                        <option value="call">Call</option>
                        <option value="meeting">Meeting</option>
                        <option value="email">Email</option>
                    </select>
                </div>
            `;
            break;
        case 'assign_contact':
            html = `
                <div class="mb-2">
                    <label class="form-label small">User ID</label>
                    <input type="number" class="form-control form-control-sm" name="action_user_${actionId}" placeholder="User ID">
                </div>
            `;
            break;
    }
    
    fieldsDiv.innerHTML = html;
}

function loadActions(actions) {
    if (!actions || !Array.isArray(actions) || actions.length === 0) return;
    
    const builder = document.getElementById('actionsBuilder');
    builder.innerHTML = '';
    actionCount = 0;
    
    actions.forEach(action => {
        actionCount++;
        addAction();
        setTimeout(() => {
            const actionDiv = document.getElementById('action_' + actionCount);
            if (actionDiv) {
                if (action.type) {
                    const typeSelect = actionDiv.querySelector(`[name="action_type_${actionCount}"]`);
                    if (typeSelect) {
                        typeSelect.value = action.type;
                        updateActionFields(actionCount);
                        
                        setTimeout(() => {
                            // Populate fields
                            if (action.message && actionDiv.querySelector(`[name="action_message_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_message_${actionCount}"]`).value = action.message;
                            }
                            if (action.template && actionDiv.querySelector(`[name="action_template_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_template_${actionCount}"]`).value = action.template;
                            }
                            if (action.params && actionDiv.querySelector(`[name="action_params_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_params_${actionCount}"]`).value = JSON.stringify(action.params);
                            }
                            if (action.tag_id && actionDiv.querySelector(`[name="action_tag_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_tag_${actionCount}"]`).value = action.tag_id;
                            }
                            if (action.stage && actionDiv.querySelector(`[name="action_stage_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_stage_${actionCount}"]`).value = action.stage;
                            }
                            if (action.note && actionDiv.querySelector(`[name="action_note_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_note_${actionCount}"]`).value = action.note;
                            }
                            if (action.note_type && actionDiv.querySelector(`[name="action_note_type_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_note_type_${actionCount}"]`).value = action.note_type;
                            }
                            if (action.user_id && actionDiv.querySelector(`[name="action_user_${actionCount}"]`)) {
                                actionDiv.querySelector(`[name="action_user_${actionCount}"]`).value = action.user_id;
                            }
                        }, 100);
                    }
                }
            }
        }, 50);
    });
}

function getActions() {
    const actions = [];
    const actionItems = document.querySelectorAll('.action-item');
    
    actionItems.forEach(item => {
        const id = item.id.replace('action_', '');
        const type = document.querySelector(`[name="action_type_${id}"]`)?.value;
        
        if (!type) return;
        
        const action = { type };
        
        switch (type) {
            case 'send_message':
                action.message = document.querySelector(`[name="action_message_${id}"]`)?.value || '';
                break;
            case 'send_template':
                action.template = document.querySelector(`[name="action_template_${id}"]`)?.value || '';
                const paramsText = document.querySelector(`[name="action_params_${id}"]`)?.value || '[]';
                try {
                    action.params = JSON.parse(paramsText);
                } catch (e) {
                    action.params = [];
                }
                break;
            case 'add_tag':
            case 'remove_tag':
                action.tag_id = parseInt(document.querySelector(`[name="action_tag_${id}"]`)?.value || 0);
                break;
            case 'change_stage':
                action.stage = document.querySelector(`[name="action_stage_${id}"]`)?.value || '';
                break;
            case 'create_note':
                action.note = document.querySelector(`[name="action_note_${id}"]`)?.value || '';
                action.note_type = document.querySelector(`[name="action_note_type_${id}"]`)?.value || 'general';
                break;
            case 'assign_contact':
                action.user_id = parseInt(document.querySelector(`[name="action_user_${id}"]`)?.value || 0);
                break;
        }
        
        actions.push(action);
    });
    
    return actions;
}

function saveWorkflow() {
    const validator = new FormValidator('workflowForm');
    if (!validator.validate()) {
        return;
    }
    
    const workflowId = document.getElementById('workflow_id').value;
    const formData = new FormData(document.getElementById('workflowForm'));
    formData.append('action', workflowId ? 'update' : 'create');
    formData.append('trigger_conditions', JSON.stringify(getTriggerConditions()));
    formData.append('actions', JSON.stringify(getActions()));
    
    fetch('workflows.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Workflow saved successfully!', 'success');
            closeWorkflowModal();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to save workflow', 'error');
            if (data.errors) {
                validator.showErrors(data.errors);
            }
        }
    })
    .catch(err => {
        showToast('Error: ' + err.message, 'error');
    });
}

function editWorkflow(id) {
    fetch('workflows.php?action=get&id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success && data.workflow) {
            workflowsData = workflowsData.map(w => w.id == id ? data.workflow : w);
            openWorkflowModal(id);
        }
    })
    .catch(() => {
        openWorkflowModal(id);
    });
}

function deleteWorkflow(id) {
    if (!confirm('Are you sure you want to delete this workflow?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('workflows.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Workflow deleted successfully!', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.error || 'Failed to delete workflow', 'error');
        }
    });
}

function toggleWorkflow(id) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    
    fetch('workflows.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Workflow status updated', 'success');
        } else {
            showToast(data.error || 'Failed to update workflow', 'error');
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

