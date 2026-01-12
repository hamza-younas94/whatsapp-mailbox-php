<?php
/**
 * Segments Management - Smart contact grouping
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

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
                $segment = Segment::create([
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? null,
                    'conditions' => json_decode($_POST['conditions'], true),
                    'is_dynamic' => isset($_POST['is_dynamic']) && $_POST['is_dynamic'] == '1',
                    'created_by' => $user->id
                ]);
                $segment->updateContactCount();
                echo json_encode(['success' => true, 'segment' => $segment]);
                break;
            
            case 'update':
                $segment = Segment::findOrFail($_POST['id']);
                $segment->update([
                    'name' => $_POST['name'],
                    'description' => $_POST['description'] ?? null,
                    'conditions' => json_decode($_POST['conditions'], true),
                    'is_dynamic' => isset($_POST['is_dynamic']) && $_POST['is_dynamic'] == '1'
                ]);
                $segment->updateContactCount();
                echo json_encode(['success' => true, 'segment' => $segment]);
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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">📊 Contact Segments</h1>
            <p class="text-muted">Smart grouping for targeted campaigns</p>
        </div>
        <button class="btn btn-primary" onclick="openSegmentModal()">
            <i class="fas fa-plus"></i> New Segment
        </button>
    </div>

    <!-- Segments Grid -->
    <div class="row">
        <?php foreach ($segments as $segment): ?>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($segment->name); ?></h5>
                    <p class="card-text text-muted small"><?php echo htmlspecialchars($segment->description ?? ''); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="mb-0"><?php echo number_format($segment->contact_count); ?></h3>
                        <span class="badge bg-<?php echo $segment->is_dynamic ? 'success' : 'secondary'; ?>">
                            <?php echo $segment->is_dynamic ? 'Dynamic' : 'Static'; ?>
                        </span>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="editSegment(<?php echo $segment->id; ?>)">Edit</button>
                        <button class="btn btn-sm btn-outline-info" onclick="refreshSegment(<?php echo $segment->id; ?>)">Refresh</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteSegment(<?php echo $segment->id; ?>)">Delete</button>
                    </div>
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
                <form id="segmentForm">
                    <input type="hidden" id="segment_id" name="id">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Segment Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conditions</label>
                        <div id="conditions-builder">
                            <div class="condition-row mb-2">
                                <select class="form-select form-select-sm d-inline-block w-auto me-2" name="field">
                                    <option value="stage">Stage</option>
                                    <option value="lead_score">Lead Score</option>
                                    <option value="total_revenue">Total Revenue</option>
                                    <option value="last_message_days">Days Since Last Message</option>
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
    segmentModal = new bootstrap.Modal(document.getElementById('segmentModal'));
});

function openSegmentModal() {
    document.getElementById('segmentForm').reset();
    document.getElementById('segment_id').value = '';
    document.getElementById('segmentModalTitle').textContent = 'New Segment';
    segmentModal.show();
}

function editSegment(id) {
    const segment = segmentsData.find(s => s.id === id);
    if (segment) {
        document.getElementById('segment_id').value = segment.id;
        document.getElementById('name').value = segment.name;
        document.getElementById('description').value = segment.description || '';
        document.getElementById('is_dynamic').checked = segment.is_dynamic;
        document.getElementById('segmentModalTitle').textContent = 'Edit Segment';
        segmentModal.show();
    }
}

function saveSegment() {
    // Build conditions object from form
    const conditions = {
        total_revenue: {operator: '>', value: 10000}
    };
    
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
            showToast('Error: ' + data.error, 'error');
        }
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
