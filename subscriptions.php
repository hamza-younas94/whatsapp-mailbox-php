<?php
/**
 * Subscription Management Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\User;
use App\Models\UserSubscription;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

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
            case 'bulk_update':
                $plan = sanitize($_POST['plan'] ?? '');
                $messageLimit = (int) ($_POST['message_limit'] ?? 1000);
                $contactLimit = (int) ($_POST['contact_limit'] ?? 500);
                
                $updated = UserSubscription::where('plan', $plan)->update([
                    'message_limit' => $messageLimit,
                    'contact_limit' => $contactLimit
                ]);
                
                echo json_encode(['success' => true, 'updated' => $updated]);
                break;
            
            case 'reset_usage':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $sub = UserSubscription::where('user_id', $userId)->first();
                if ($sub) {
                    $sub->update([
                        'messages_used' => 0,
                        'current_period_start' => date('Y-m-d H:i:s'),
                        'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days'))
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Usage reset successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Subscription not found']);
                }
                break;
            
            case 'extend_trial':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $days = (int) ($_POST['days'] ?? 14);
                $sub = UserSubscription::where('user_id', $userId)->first();
                if ($sub) {
                    $sub->update([
                        'status' => 'trial',
                        'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$days} days"))
                    ]);
                    echo json_encode(['success' => true, 'message' => "Trial extended by {$days} days"]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Subscription not found']);
                }
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$pageTitle = 'Subscription Management';
$pageDescription = 'Manage user subscriptions, plans, and limits';

// Get all subscriptions with user data
$subscriptions = Capsule::table('user_subscriptions')
    ->leftJoin('users', 'user_subscriptions.user_id', '=', 'users.id')
    ->select(
        'user_subscriptions.*',
        'users.username',
        'users.email',
        'users.full_name',
        'users.is_active'
    )
    ->orderBy('user_subscriptions.created_at', 'desc')
    ->get();

// Get subscription stats
$stats = [
    'total' => $subscriptions->count(),
    'active' => $subscriptions->where('status', 'active')->count(),
    'trial' => $subscriptions->where('status', 'trial')->count(),
    'expired' => $subscriptions->where('status', 'expired')->count(),
    'free' => $subscriptions->where('plan', 'free')->count(),
    'starter' => $subscriptions->where('plan', 'starter')->count(),
    'pro' => $subscriptions->where('plan', 'pro')->count(),
    'enterprise' => $subscriptions->where('plan', 'enterprise')->count(),
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>💳 Subscription Management</h1>
            <p class="text-muted">Manage tenant subscriptions, plans, and usage limits</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn-secondary" onclick="openBulkUpdateModal()">📊 Bulk Update Plans</button>
            <a class="btn-primary" href="users.php">👥 User Management</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Subscriptions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🆓</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['trial']); ?></div>
                <div class="stat-label">Trial</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⚠️</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['expired']); ?></div>
                <div class="stat-label">Expired</div>
            </div>
        </div>
    </div>

    <!-- Plans Breakdown -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🆓</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['free']); ?></div>
                <div class="stat-label">Free Plan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🚀</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['starter']); ?></div>
                <div class="stat-label">Starter Plan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💼</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['pro']); ?></div>
                <div class="stat-label">Professional Plan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div>
                <div class="stat-value"><?php echo number_format($stats['enterprise']); ?></div>
                <div class="stat-label">Enterprise Plan</div>
            </div>
        </div>
    </div>

    <!-- Subscriptions Table -->
    <div class="card">
        <h3>All Subscriptions</h3>
        <div class="table-responsive">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Messages</th>
                        <th>Contacts</th>
                        <th>Period</th>
                        <th>Trial Ends</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($sub->full_name ?? $sub->username); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($sub->email); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $sub->plan === 'free' ? 'secondary' : 
                                        ($sub->plan === 'starter' ? 'info' : 
                                        ($sub->plan === 'pro' ? 'warning' : 'success')); 
                                ?>">
                                    <?php echo strtoupper($sub->plan); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $sub->status === 'active' ? 'success' : 
                                        ($sub->status === 'trial' ? 'info' : 'danger'); 
                                ?>">
                                    <?php echo strtoupper($sub->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $msgUsage = $sub->message_limit > 0 ? ($sub->messages_used / $sub->message_limit * 100) : 0;
                                $msgColor = $msgUsage > 80 ? 'red' : ($msgUsage > 50 ? 'orange' : 'green');
                                ?>
                                <div style="font-size: 12px;">
                                    <?php echo number_format($sub->messages_used); ?> / <?php echo number_format($sub->message_limit); ?>
                                    <div style="width: 100%; background: #f1f5f9; border-radius: 4px; height: 4px; margin-top: 3px;">
                                        <div style="width: <?php echo min(100, $msgUsage); ?>%; background: <?php echo $msgColor; ?>; height: 100%; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo number_format($sub->contact_limit); ?></td>
                            <td>
                                <small>
                                    <?php echo $sub->current_period_start ? date('M d', strtotime($sub->current_period_start)) : 'N/A'; ?> - 
                                    <?php echo $sub->current_period_end ? date('M d', strtotime($sub->current_period_end)) : 'N/A'; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($sub->trial_ends_at): ?>
                                    <small><?php echo date('M d, Y', strtotime($sub->trial_ends_at)); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="user-profile.php?user_id=<?php echo $sub->user_id; ?>" class="btn-icon" title="View Profile">👁️</a>
                                    <button onclick="resetUsage(<?php echo $sub->user_id; ?>)" class="btn-icon" title="Reset Usage">🔄</button>
                                    <button onclick="extendTrial(<?php echo $sub->user_id; ?>)" class="btn-icon" title="Extend Trial">⏰</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div id="bulkUpdateModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Bulk Update Plan Limits</h2>
            <button onclick="closeBulkUpdateModal()" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="bulkUpdateForm">
                <div class="form-group">
                    <label>Plan <span class="text-danger">*</span></label>
                    <select name="plan" required>
                        <option value="free">Free</option>
                        <option value="starter">Starter</option>
                        <option value="pro">Professional</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message Limit <span class="text-danger">*</span></label>
                    <input type="number" name="message_limit" required min="0">
                </div>
                <div class="form-group">
                    <label>Contact Limit <span class="text-danger">*</span></label>
                    <input type="number" name="contact_limit" required min="0">
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary" style="flex: 1;">Update All</button>
                    <button type="button" onclick="closeBulkUpdateModal()" class="btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; display: flex; align-items: center; gap: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.stat-icon { font-size: 32px; }
.stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
.stat-label { font-size: 14px; color: #6b7280; }
.btn-icon { background: transparent; border: none; font-size: 18px; cursor: pointer; padding: 4px 8px; }
.btn-icon:hover { background: #f1f5f9; border-radius: 4px; }
</style>

<script>
function openBulkUpdateModal() {
    document.getElementById('bulkUpdateModal').style.display = 'flex';
}

function closeBulkUpdateModal() {
    document.getElementById('bulkUpdateModal').style.display = 'none';
}

document.getElementById('bulkUpdateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'bulk_update');
    
    try {
        const response = await fetch('subscriptions.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Updated ${result.updated} subscriptions`);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    } catch (error) {
        alert('❌ Request failed: ' + error.message);
    }
});

function resetUsage(userId) {
    if (!confirm('Reset message usage for this user? This will start a new billing period.')) return;
    
    const formData = new FormData();
    formData.append('action', 'reset_usage');
    formData.append('user_id', userId);
    
    fetch('subscriptions.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Usage reset successfully');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(e => alert('❌ Request failed: ' + e.message));
}

function extendTrial(userId) {
    const days = prompt('Extend trial by how many days?', '14');
    if (!days) return;
    
    const formData = new FormData();
    formData.append('action', 'extend_trial');
    formData.append('user_id', userId);
    formData.append('days', days);
    
    fetch('subscriptions.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(e => alert('❌ Request failed: ' + e.message));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
