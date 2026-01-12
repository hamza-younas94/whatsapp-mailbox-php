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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $tag = Tag::create([
                    'name' => $_POST['name'],
                    'color' => $_POST['color'] ?? '#25D366',
                    'description' => $_POST['description'] ?? null
                ]);
                echo json_encode(['success' => true, 'tag' => $tag]);
                break;
            
            case 'update':
                $tag = Tag::findOrFail($_POST['id']);
                $tag->update([
                    'name' => $_POST['name'],
                    'color' => $_POST['color'],
                    'description' => $_POST['description'] ?? null
                ]);
                echo json_encode(['success' => true, 'tag' => $tag]);
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
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">🏷️ Tags Management</h1>
            <p class="text-muted">Organize and categorize your contacts</p>
        </div>
        <button class="btn btn-primary" onclick="openTagModal()">
            <i class="fas fa-plus"></i> New Tag
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
                        <label for="tag_name" class="form-label">Tag Name</label>
                        <input type="text" class="form-control" id="tag_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tag_color" class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color" id="tag_color" name="color" value="#25D366">
                    </div>
                    
                    <div class="mb-3">
                        <label for="tag_description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="tag_description" name="description" rows="2"></textarea>
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
    // Find tag data from the page
    fetch(`api.php?action=getTag&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tag_id').value = data.tag.id;
                document.getElementById('tag_name').value = data.tag.name;
                document.getElementById('tag_color').value = data.tag.color;
                document.getElementById('tag_description').value = data.tag.description || '';
                document.getElementById('tagModalTitle').textContent = 'Edit Tag';
                tagModal.show();
            }
        });
}

function saveTag() {
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
            showToast('Error: ' + data.error, 'error');
        }
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
