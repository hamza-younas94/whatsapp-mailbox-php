<?php
/**
 * Tags Management Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Tag;
use App\Models\Contact;

// Check if user is authenticated
if (!isAuthenticated()) {
    redirect('/login.php');
}

$user = getCurrentUser();

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    // Handle GET requests for fetching data
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        try {
            if ($_GET['action'] === 'getTag' && isset($_GET['id'])) {
                $tag = Tag::findOrFail($_GET['id']);
                echo json_encode(['success' => true, 'tag' => $tag]);
                exit;
            }
            
            if ($_GET['action'] === 'list') {
                $tags = Tag::withCount('contacts')->orderBy('name')->get()->map(function($tag){
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                        'description' => $tag->description,
                        'contacts_count' => $tag->contacts_count
                    ];
                });
                echo json_encode(['success' => true, 'tags' => $tags]);
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
            case 'update':
                // Validate input
                $validation = validate([
                    'name' => sanitize($_POST['name'] ?? ''),
                    'description' => sanitize($_POST['description'] ?? '')
                ], [
                    'name' => 'required|min:2|max:50',
                    'description' => 'max:255'
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
                    'name' => sanitize($_POST['name']),
                    'color' => sanitize($_POST['color'] ?? '#25D366'),
                    'description' => !empty($_POST['description']) ? sanitize($_POST['description']) : null
                ];
                
                if ($action === 'create') {
                    $tag = Tag::create($data);
                    echo json_encode(['success' => true, 'tag' => $tag]);
                } else {
                    $tag = Tag::findOrFail($_POST['id']);
                    $tag->update($data);
                    echo json_encode(['success' => true, 'tag' => $tag]);
                }
                break;
            
            case 'delete':
                $tag = Tag::findOrFail($_POST['id']);
                $tag->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'assign':
                $contact = Contact::findOrFail($_POST['contact_id']);
                $tagIds = $_POST['tag_ids'] ?? [];
                $contact->contactTags()->sync($tagIds);
                echo json_encode(['success' => true]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (\Illuminate\Database\QueryException $e) {
        // Handle duplicate entry or database errors
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode([
                'success' => false,
                'error' => 'A tag with this name already exists',
                'errors' => ['name' => ['A tag with this name already exists']]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
    }
}

// Fetch all tags with contact counts
$tags = Tag::withCount('contacts')->orderBy('name')->get();

// Get stats
$totalTags = $tags->count();
$totalTaggedContacts = Contact::has('contactTags')->count();

// Render page
$pageTitle = 'Tags Management';
$pageDescription = 'Organize and categorize your contacts';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>🏷️ Tags Management</h1>
            <p>Organize and categorize your contacts</p>
        </div>
        <button class="btn btn-primary" onclick="openTagModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            New Tag
        </button>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Total Tags</h5>
                    <h2 class="mb-0"><?php echo $totalTags; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Tagged Contacts</h5>
                    <h2 class="mb-0"><?php echo $totalTaggedContacts; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tags Grid -->
    <div class="card">
        <div class="card-body">
            <div class="row" id="tags-grid">
                <?php foreach ($tags as $tag): ?>
                <div class="col-md-4 mb-3">
                    <div class="card tag-card" style="border-left: 4px solid <?php echo htmlspecialchars($tag->color); ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <span class="badge" style="background-color: <?php echo htmlspecialchars($tag->color); ?>">
                                            <?php echo htmlspecialchars($tag->name); ?>
                                        </span>
                                    </h5>
                                    <?php if ($tag->description): ?>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($tag->description); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-0">
                                        <small class="text-muted">
                                            <i class="fas fa-users"></i> <?php echo $tag->contacts_count; ?> contacts
                                        </small>
                                    </p>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editTag(<?php echo $tag->id; ?>)"><i class="fas fa-edit"></i> Edit</a></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteTag(<?php echo $tag->id; ?>)"><i class="fas fa-trash"></i> Delete</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tag Modal -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tagModalTitle">New Tag</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="tagForm">
                    <input type="hidden" id="tag_id" name="id">
                    
                    <div class="mb-3">
                        <label for="tag_name" class="form-label">Tag Name *</label>
                        <input type="text" class="form-control crm-input" id="tag_name" name="name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="tag_color" class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="tag_color" name="color" value="#25D366">
                    </div>
                    
                    <div class="mb-3">
                        <label for="tag_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control crm-textarea" id="tag_description" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTag()">Save Tag</button>
            </div>
        </div>
    </div>
</div>

<script>
let tagModal;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('tagModal');
    if (modalElement) {
        tagModal = new bootstrap.Modal(modalElement);
    }
});

function openTagModal() {
    if (!tagModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('tagForm').reset();
    document.getElementById('tag_id').value = '';
    document.getElementById('tagModalTitle').textContent = 'New Tag';
    tagModal.show();
}

function editTag(id) {
    if (!tagModal) {
        console.error('Modal not initialized');
        return;
    }
    // Find tag data from the page
    fetch(`tags.php?action=getTag&id=${id}`, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tag_id').value = data.tag.id;
                document.getElementById('tag_name').value = data.tag.name;
                document.getElementById('tag_color').value = data.tag.color;
                document.getElementById('tag_description').value = data.tag.description || '';
                document.getElementById('tagModalTitle').textContent = 'Edit Tag';
                tagModal.show();
            } else {
                showToast('Error loading tag: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            showToast('Error loading tag', 'error');
        });
}

// Initialize tag form validator
let tagValidator;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof FormValidator !== 'undefined') {
        tagValidator = new FormValidator('tagForm', {
            name: ['required', 'min:2', 'max:50'],
            description: ['max:255']
        });
    } else {
        console.error('FormValidator is not defined. Make sure validation.js is loaded.');
    }
});

function saveTag() {
    if (!tagValidator || !tagValidator.validate()) {
        return;
    }
    
    const formData = new FormData(document.getElementById('tagForm'));
    const tagId = document.getElementById('tag_id').value;
    
    formData.append('action', tagId ? 'update' : 'create');
    
    fetch('tags.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Tag saved successfully!', 'success');
            tagModal.hide();
            location.reload();
        } else {
            // Handle validation errors from backend
            if (data.errors && tagValidator) {
                tagValidator.setErrors(data.errors);
            } else {
                showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save tag', 'error');
    });
}

function deleteTag(id) {
    if (!confirm('Are you sure you want to delete this tag? It will be removed from all contacts.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('tags.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Tag deleted successfully!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
