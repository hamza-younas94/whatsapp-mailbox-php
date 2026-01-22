<?php
/**
 * User Management Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\User;

// Check if user is authenticated
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Check if user has admin permissions
if (!isAdmin()) {
    header('Location: index.php');
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
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
                    'username' => sanitize($_POST['username'] ?? ''),
                    'email' => sanitize($_POST['email'] ?? ''),
                    'full_name' => sanitize($_POST['full_name'] ?? ''),
                    'password' => $_POST['password'] ?? '',
                    'role' => sanitize($_POST['role'] ?? 'agent')
                ], [
                    'username' => 'required|min:3|max:50',
                    'email' => 'required|email|max:255',
                    'full_name' => 'required|min:2|max:100',
                    'password' => ($action === 'create' ? 'required|min:6' : 'min:6'),
                    'role' => 'required|in:admin,agent,viewer'
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
                    'username' => sanitize($_POST['username']),
                    'email' => sanitize($_POST['email']),
                    'full_name' => sanitize($_POST['full_name']),
                    'role' => sanitize($_POST['role']),
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1',
                    'phone' => sanitize($_POST['phone'] ?? '')
                ];
                
                // Only update password if provided
                if (!empty($_POST['password'])) {
                    $data['password'] = $_POST['password']; // Will be hashed by model
                }
                
                if ($action === 'create') {
                    // Check if username or email already exists
                    if (User::where('username', $data['username'])->exists()) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Username already exists',
                            'errors' => ['username' => ['Username already taken']]
                        ]);
                        exit;
                    }
                    
                    if (User::where('email', $data['email'])->exists()) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Email already exists',
                            'errors' => ['email' => ['Email already taken']]
                        ]);
                        exit;
                    }
                    
                    $newUser = User::create($data);
                    
                    // Create subscription based on selected plan
                    $subscriptionPlan = sanitize($_POST['subscription_plan'] ?? 'starter');
                    $planLimits = [
                        'free' => ['message_limit' => 100, 'contact_limit' => 50],
                        'starter' => ['message_limit' => 1000, 'contact_limit' => 500],
                        'professional' => ['message_limit' => 10000, 'contact_limit' => 5000],
                        'enterprise' => ['message_limit' => 999999, 'contact_limit' => 999999]
                    ];
                    
                    $limits = $planLimits[$subscriptionPlan] ?? $planLimits['starter'];
                    
                    // Default features per plan
                    $planFeatures = [
                        'free' => [
                            'mailbox' => true, 'quick_replies' => false, 'broadcasts' => false,
                            'segments' => false, 'drip_campaigns' => false, 'scheduled_messages' => false,
                            'auto_reply' => true, 'tags' => true, 'notes' => true,
                            'message_templates' => false, 'crm' => false, 'analytics' => false,
                            'workflows' => false, 'dcmb_ip_commands' => true
                        ],
                        'starter' => [
                            'mailbox' => true, 'quick_replies' => true, 'broadcasts' => true,
                            'segments' => false, 'drip_campaigns' => false, 'scheduled_messages' => true,
                            'auto_reply' => true, 'tags' => true, 'notes' => true,
                            'message_templates' => true, 'crm' => true, 'analytics' => false,
                            'workflows' => false, 'dcmb_ip_commands' => true
                        ],
                        'professional' => [
                            'mailbox' => true, 'quick_replies' => true, 'broadcasts' => true,
                            'segments' => true, 'drip_campaigns' => true, 'scheduled_messages' => true,
                            'auto_reply' => true, 'tags' => true, 'notes' => true,
                            'message_templates' => true, 'crm' => true, 'analytics' => true,
                            'workflows' => true, 'dcmb_ip_commands' => true
                        ],
                        'enterprise' => [
                            'mailbox' => true, 'quick_replies' => true, 'broadcasts' => true,
                            'segments' => true, 'drip_campaigns' => true, 'scheduled_messages' => true,
                            'auto_reply' => true, 'tags' => true, 'notes' => true,
                            'message_templates' => true, 'crm' => true, 'analytics' => true,
                            'workflows' => true, 'dcmb_ip_commands' => true
                        ]
                    ];
                    
                    \App\Models\UserSubscription::create([
                        'user_id' => $newUser->id,
                        'plan' => $subscriptionPlan,
                        'status' => 'trial',
                        'message_limit' => $limits['message_limit'],
                        'messages_used' => 0,
                        'contact_limit' => $limits['contact_limit'],
                        'features' => $planFeatures[$subscriptionPlan] ?? $planFeatures['starter'],
                        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
                        'current_period_start' => date('Y-m-d H:i:s'),
                        'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days'))
                    ]);
                    
                    // Create default user settings
                    \App\Models\UserSettings::create([
                        'user_id' => $newUser->id,
                        'whatsapp_api_version' => 'v18.0',
                        'is_configured' => false
                    ]);
                    
                    echo json_encode(['success' => true, 'user' => $newUser]);
                } else {
                    $userToUpdate = User::findOrFail($_POST['id']);
                    
                    // Check for duplicate username/email (excluding current user)
                    if (User::where('username', $data['username'])->where('id', '!=', $userToUpdate->id)->exists()) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Username already exists',
                            'errors' => ['username' => ['Username already taken']]
                        ]);
                        exit;
                    }
                    
                    if (User::where('email', $data['email'])->where('id', '!=', $userToUpdate->id)->exists()) {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Email already exists',
                            'errors' => ['email' => ['Email already taken']]
                        ]);
                        exit;
                    }
                    
                    $userToUpdate->update($data);
                    echo json_encode(['success' => true, 'user' => $userToUpdate]);
                }
                break;
            
            case 'delete':
                $userToDelete = User::findOrFail($_POST['id']);
                
                // Prevent deleting yourself
                if ($userToDelete->id == $user->id) {
                    echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
                    exit;
                }
                
                $userToDelete->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $userToToggle = User::findOrFail($_POST['id']);
                
                // Prevent deactivating yourself
                if ($userToToggle->id == $user->id && !$userToToggle->is_active) {
                    echo json_encode(['success' => false, 'error' => 'You cannot deactivate your own account']);
                    exit;
                }
                
                $userToToggle->update(['is_active' => !$userToToggle->is_active]);
                echo json_encode(['success' => true, 'is_active' => $userToToggle->is_active]);
                break;
            
            case 'reset_password':
                $userToReset = User::findOrFail($_POST['id']);
                $newPassword = $_POST['new_password'] ?? '';
                
                $validation = validate(['new_password' => $newPassword], [
                    'new_password' => 'required|min:6'
                ]);
                
                if ($validation !== true) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validation
                    ]);
                    exit;
                }
                
                $userToReset->update(['password' => $newPassword]);
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

// Fetch all users
$users = User::orderBy('created_at', 'desc')->get();

// Render page
$pageTitle = 'User Management';
$pageDescription = 'Manage system users and permissions';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>👥 User Management</h1>
            <p class="text-muted">Manage system users, roles, and permissions</p>
        </div>
        <button onclick="openUserModal()" class="btn-primary">+ Add User</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u->id); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($u->username); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($u->full_name ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($u->email ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $u->role === 'admin' ? 'danger' : 
                                    ($u->role === 'agent' ? 'primary' : 'secondary'); 
                            ?>">
                                <?php echo strtoupper($u->role); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $u->is_active ? 'success' : 'danger'; ?>">
                                <?php echo $u->is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                                echo $u->last_login_at ? 
                                    date('M d, Y H:i', strtotime($u->last_login_at)) : 
                                    'Never'; 
                            ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="user-profile.php?user_id=<?php echo $u->id; ?>" class="btn-secondary btn-sm" title="View Profile">👁️</a>
                                <button onclick="editUser(<?php echo $u->id; ?>)" class="btn-secondary btn-sm" title="Edit">
                                    ✏️
                                </button>
                                <button onclick="toggleUser(<?php echo $u->id; ?>)" 
                                        class="btn-secondary btn-sm" 
                                        title="<?php echo $u->is_active ? 'Deactivate' : 'Activate'; ?>">
                                    <?php echo $u->is_active ? '🚫' : '✅'; ?>
                                </button>
                                <button onclick="resetPassword(<?php echo $u->id; ?>)" 
                                        class="btn-secondary btn-sm" 
                                        title="Reset Password">
                                    🔑
                                </button>
                                <?php if ($u->id != $user->id): ?>
                                <button onclick="deleteUser(<?php echo $u->id; ?>)" 
                                        class="btn-danger btn-sm" 
                                        title="Delete">
                                    🗑️
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="userModalTitle">Add User</h2>
            <button onclick="closeUserModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userId" name="id">
                
                <div class="form-group">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" id="username" name="username" class="crm-input" required>
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="fullName" name="full_name" class="crm-input" required>
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label>Email <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="crm-input" required>
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" id="phone" name="phone" class="crm-input">
                </div>
                
                <div class="form-group">
                    <label>Role <span class="text-danger">*</span></label>
                    <select id="role" name="role" class="crm-select" required>
                        <option value="agent">Agent</option>
                        <option value="admin">Admin</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                
                <div class="form-group" id="subscriptionPlanGroup">
                    <label>Subscription Plan <span class="text-danger">*</span></label>
                    <select id="subscriptionPlan" name="subscription_plan" class="crm-select">
                        <option value="free">Free (100 msgs/month, 50 contacts)</option>
                        <option value="starter" selected>Starter (1,000 msgs/month, 500 contacts)</option>
                        <option value="professional">Professional (10,000 msgs/month, 5,000 contacts)</option>
                        <option value="enterprise">Enterprise (Unlimited)</option>
                    </select>
                    <small class="text-muted">Select subscription plan for new organization</small>
                </div>
                
                <div class="form-group">
                    <label id="passwordLabel">Password <span class="text-danger">*</span></label>
                    <input type="password" id="password" name="password" class="crm-input">
                    <small class="text-muted">Leave empty when editing to keep current password</small>
                    <div class="invalid-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="isActive" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">Save</button>
                    <button type="button" onclick="closeUserModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/validation.js"></script>
<script>
let userValidator = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validator
    userValidator = new FormValidator('userForm', {
        'username': 'required|min:3|max:50',
        'full_name': 'required|min:2|max:100',
        'email': 'required|email|max:255',
        'password': function(value, field) {
            const isCreate = !document.getElementById('userId').value;
            if (isCreate && !value) {
                return 'Password is required';
            }
            if (value && value.length < 6) {
                return 'Password must be at least 6 characters';
            }
            return null;
        },
        'role': 'required'
    });
    
    // Handle form submission
    document.getElementById('userForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!userValidator.validate()) {
            return;
        }
        
        const formData = new FormData(this);
        const data = {
            action: document.getElementById('userId').value ? 'update' : 'create',
            id: document.getElementById('userId').value || null
        };
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        try {
            const response = await fetch('users.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast('User saved successfully!', 'success');
                closeUserModal();
                location.reload();
            } else {
                if (result.errors) {
                    userValidator.setErrors(result.errors);
                }
                showToast('Failed to save user: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('Error saving user:', error);
            showToast('Failed to save user', 'error');
        }
    });
});

function openUserModal(userId = null) {
    const modal = document.getElementById('userModal');
    const title = document.getElementById('userModalTitle');
    const form = document.getElementById('userForm');
    const subscriptionGroup = document.getElementById('subscriptionPlanGroup');
    
    if (userId) {
        title.textContent = 'Edit User';
        subscriptionGroup.style.display = 'none'; // Hide subscription on edit
        loadUser(userId);
    } else {
        title.textContent = 'Add User';
        subscriptionGroup.style.display = 'block'; // Show subscription on create
        form.reset();
        document.getElementById('userId').value = '';
        document.getElementById('passwordLabel').innerHTML = 'Password <span class="text-danger">*</span>';
    }
    
    modal.style.display = 'flex';
    modal.classList.add('show');
    document.body.classList.add('modal-open');
}

async function loadUser(userId) {
    try {
        const response = await fetch(`api.php/users/${userId}`);
        const result = await response.json();
        
        if (result.success && result.user) {
            const u = result.user;
            document.getElementById('userId').value = u.id;
            document.getElementById('username').value = u.username || '';
            document.getElementById('fullName').value = u.full_name || '';
            document.getElementById('email').value = u.email || '';
            document.getElementById('phone').value = u.phone || '';
            document.getElementById('role').value = u.role || 'agent';
            document.getElementById('isActive').checked = u.is_active !== false;
            document.getElementById('passwordLabel').innerHTML = 'Password <small class="text-muted">(leave empty to keep current)</small>';
        }
    } catch (error) {
        console.error('Error loading user:', error);
        showToast('Failed to load user', 'error');
    }
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    modal.style.display = 'none';
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
}

async function editUser(userId) {
    openUserModal(userId);
}

async function toggleUser(userId) {
    if (!confirm('Are you sure you want to toggle this user\'s status?')) {
        return;
    }
    
    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ action: 'toggle', id: userId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('User status updated!', 'success');
            location.reload();
        } else {
            showToast('Failed to update user: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error toggling user:', error);
        showToast('Failed to update user', 'error');
    }
}

async function resetPassword(userId) {
    const newPassword = prompt('Enter new password (minimum 6 characters):');
    if (!newPassword || newPassword.length < 6) {
        showToast('Password must be at least 6 characters', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to reset this user\'s password?')) {
        return;
    }
    
    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ 
                action: 'reset_password', 
                id: userId,
                new_password: newPassword
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Password reset successfully!', 'success');
        } else {
            showToast('Failed to reset password: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error resetting password:', error);
        showToast('Failed to reset password', 'error');
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('users.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({ action: 'delete', id: userId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('User deleted successfully!', 'success');
            location.reload();
        } else {
            showToast('Failed to delete user: ' + (result.error || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showToast('Failed to delete user', 'error');
    }
}

// Modals should NOT close when clicking outside - removed backdrop click handler
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>

