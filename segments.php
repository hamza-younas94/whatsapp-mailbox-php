<?php
/**
 * Segments Management - Smart contact grouping
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Segment;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Handle AJAX
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
                    'description' => sanitize($_POST['description'] ?? ''),
                    'conditions' => $_POST['conditions'] ?? '{}'
                ], [
                    'name' => 'required|min:2|max:100',
                    'description' => 'max:500',
                    'conditions' => 'required'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                // Validate conditions JSON
                $conditions = json_decode($_POST['conditions'], true);
                if (json_last_error() !== JSON_ERROR_NONE || empty($conditions)) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid conditions format',
                        'errors' => ['conditions' => ['Please provide valid conditions']]
                    ]);
                    exit;
                }
                
                $data = [
                    'name' => sanitize($_POST['name']),
                    'description' => !empty($_POST['description']) ? sanitize($_POST['description']) : null,
                    'conditions' => $conditions,
                    'is_dynamic' => isset($_POST['is_dynamic']) && $_POST['is_dynamic'] == '1'
                ];
                
                if ($action === 'create') {
                    $data['created_by'] = $user->id;
                    $segment = Segment::create($data);
                    $segment->updateContactCount();
                    echo json_encode(['success' => true, 'segment' => $segment]);
                } else {
                    $segment = Segment::findOrFail($_POST['id']);
                    $segment->update($data);
                    $segment->updateContactCount();
                    echo json_encode(['success' => true, 'segment' => $segment]);
                }
                break;
            
            case 'delete':
                Segment::findOrFail($_POST['id'])->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'refresh':
                $segment = Segment::findOrFail($_POST['id']);
                $segment->updateContactCount();
                echo json_encode(['success' => true, 'count' => $segment->contact_count]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$segments = Segment::with('creator')->get();

$pageTitle = 'Contact Segments';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid segments-page">
    <div class="page-header">
        <div>
            <h1>📊 Contact Segments</h1>
            <p>Smart grouping for targeted campaigns</p>
        </div>
        <button class="btn btn-primary" onclick="openSegmentModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            New Segment
        </button>
    </div>

    <!-- Segments Grid -->
    <div class="row">
        <?php foreach ($segments as $segment): ?>
        <div class="col-md-4 mb-3">
            <div class="card stat-card">
                <div class="stat-icon" style="background: <?php echo $segment->is_dynamic ? '#e8f5e9' : '#f5f5f5'; ?>;">
                    <i class="fas fa-<?php echo $segment->is_dynamic ? 'sync-alt' : 'layer-group'; ?>" 
                       style="color: <?php echo $segment->is_dynamic ? '#25D366' : '#6c757d'; ?>; font-size: 1.5rem;"></i>
                </div>
                <h5 class="mb-2" style="font-size: 1.1rem; font-weight: 600; color: #1a1a1a;"><?php echo htmlspecialchars($segment->name); ?></h5>
                <p class="mb-3" style="font-size: 0.875rem; color: #6c757d; line-height: 1.4;"><?php echo htmlspecialchars($segment->description ?? 'No description'); ?></p>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="stat-value"><?php echo number_format($segment->contact_count); ?></div>
                    <span class="badge bg-<?php echo $segment->is_dynamic ? 'success' : 'secondary'; ?>">
                        <?php echo $segment->is_dynamic ? 'Dynamic' : 'Static'; ?>
                    </span>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-outline-primary" onclick="editSegment(<?php echo $segment->id; ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-info" onclick="refreshSegment(<?php echo $segment->id; ?>)">
                        <i class="fas fa-sync"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSegment(<?php echo $segment->id; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Segment Modal -->
<div class="modal fade" id="segmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="segmentModalTitle">New Segment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="segmentForm" class="needs-validation" novalidate>
                    <input type="hidden" id="segment_id" name="id">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Segment Name *</label>
                        <input type="text" class="form-control crm-input" id="name" name="name">
                        <div class="invalid-feedback">Please enter a segment name</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control crm-textarea" id="description" name="description" rows="2"></textarea>
                        <div class="invalid-feedback"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conditions</label>
                        <div id="conditions-builder">
                            <div class="condition-row mb-2">
                                <select class="form-select form-select-sm d-inline-block w-auto me-2" name="field">
                                    <option value="stage">Stage</option>
                                    <option value="lead_score">Lead Score</option>
                                    <option value="last_message_days">Days Since Last Message</option>
                                    <option value="tags">Tags</option>
                                </select>
                                <select class="form-select form-select-sm d-inline-block w-auto me-2" name="operator">
                                    <option value="=">=</option>
                                    <option value=">">></option>
                                    <option value="<"><</option>
                                    <option value="in">In</option>
                                </select>
                                <input type="text" class="form-control form-control-sm d-inline-block w-auto" name="value" placeholder="Value">
                            </div>
                        </div>
                        <small class="text-muted">Dynamic segments auto-update based on conditions</small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_dynamic" name="is_dynamic" value="1" checked>
                        <label class="form-check-label" for="is_dynamic">Dynamic (auto-update)</label>
                    </div>
                    
                    <input type="hidden" id="conditions" name="conditions">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSegment()">Save Segment</button>
            </div>
        </div>
    </div>
</div>

<script>
let segmentModal;
const segmentsData = <?php echo json_encode($segments); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('segmentModal');
    if (modalElement) {
        segmentModal = new bootstrap.Modal(modalElement);
    }
});

function openSegmentModal() {
    if (!segmentModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('segmentForm').reset();
    document.getElementById('segment_id').value = '';
    document.getElementById('segmentModalTitle').textContent = 'New Segment';
    segmentModal.show();
}

function editSegment(id) {
    if (!segmentModal) {
        console.error('Modal not initialized');
        return;
    }
    const segment = segmentsData.find(s => s.id === id);
    if (segment) {
        document.getElementById('segment_id').value = segment.id;
        document.getElementById('name').value = segment.name;
        document.getElementById('description').value = segment.description || '';
        document.getElementById('is_dynamic').checked = segment.is_dynamic;
        document.getElementById('segmentModalTitle').textContent = 'Edit Segment';
        
        // Populate conditions if they exist
        if (segment.conditions && typeof segment.conditions === 'object') {
            const conditionBuilder = document.getElementById('conditions-builder');
            const firstCondition = Object.keys(segment.conditions)[0];
            
            if (firstCondition) {
                const conditionData = segment.conditions[firstCondition];
                const fieldSelect = conditionBuilder.querySelector('select[name="field"]');
                const operatorSelect = conditionBuilder.querySelector('select[name="operator"]');
                const valueInput = conditionBuilder.querySelector('input[name="value"]');
                
                if (fieldSelect) fieldSelect.value = firstCondition;
                if (operatorSelect && conditionData.operator) operatorSelect.value = conditionData.operator;
                if (valueInput && conditionData.value) valueInput.value = conditionData.value;
            }
        }
        
        segmentModal.show();
    }
}

// Initialize segment form validator
let segmentValidator;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof FormValidator !== 'undefined') {
        segmentValidator = new FormValidator('segmentForm', {
            name: ['required', 'min:2', 'max:100'],
            description: ['max:500']
        });
    } else {
        console.error('FormValidator is not defined. Make sure validation.js is loaded.');
    }
});

function saveSegment() {
    // Validate form
    if (segmentValidator && !segmentValidator.validate()) {
        return;
    }
    
    // Fallback validation
    const name = document.getElementById('name').value.trim();
    if (!name) {
        const nameField = document.getElementById('name');
        nameField.classList.add('is-invalid');
        nameField.nextElementSibling.textContent = 'Please enter a segment name';
        showToast('Please enter a segment name', 'error');
        return;
    }
    
    // Build conditions object from form
    const conditionBuilder = document.getElementById('conditions-builder');
    const fieldSelect = conditionBuilder.querySelector('select[name="field"]');
    const operatorSelect = conditionBuilder.querySelector('select[name="operator"]');
    const valueInput = conditionBuilder.querySelector('input[name="value"]');
    
    const field = fieldSelect ? fieldSelect.value : 'stage';
    const operator = operatorSelect ? operatorSelect.value : '=';
    const value = valueInput ? valueInput.value.trim() : '';
    
    // Validate condition value
    if (!value) {
        if (valueInput) {
            valueInput.classList.add('is-invalid');
            showToast('Please enter a condition value', 'error');
            return;
        }
    }
    
    const conditions = {};
    conditions[field] = {operator: operator, value: value};
    
    const formData = new FormData(document.getElementById('segmentForm'));
    const segmentId = document.getElementById('segment_id').value;
    
    formData.append('action', segmentId ? 'update' : 'create');
    formData.append('conditions', JSON.stringify(conditions));
    
    fetch('segments.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Segment saved successfully!', 'success');
            segmentModal.hide();
            location.reload();
        } else {
            // Handle validation errors from backend
            if (data.errors && segmentValidator) {
                segmentValidator.setErrors(data.errors);
            } else {
                showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save segment', 'error');
    });
}

function deleteSegment(id) {
    if (!confirm('Delete this segment?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('segments.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Segment deleted!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function refreshSegment(id) {
    const formData = new FormData();
    formData.append('action', 'refresh');
    formData.append('id', id);
    
    fetch('segments.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Segment refreshed: ${data.count} contacts`, 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
