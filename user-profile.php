<?php
/**
 * User Profile & Tenant Settings
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\User;

// Auth + admin gate
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}
if (!isAdmin()) {
    header('Location: index.php');
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    exit;
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (!$userId) {
    header('Location: users.php');
    exit;
}

$profileUser = User::findOrFail($userId);

// Safe fetch helpers
function fetchSingle($table, $userId)
{
    try {
        return Capsule::table($table)->where('user_id', $userId)->first();
    } catch (\Exception $e) {
        return null;
    }
}

function fetchMany($table, $userId, $limit = 10)
{
    try {
        return Capsule::table($table)
            ->where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    } catch (\Exception $e) {
        return collect();
    }
}

$userSettings     = fetchSingle('user_settings', $userId);
$userApiCreds     = fetchSingle('user_api_credentials', $userId);
$userPreferences  = fetchSingle('user_preferences', $userId);
$userSubscription = fetchSingle('user_subscriptions', $userId);
$userUsageLogs    = fetchMany('user_usage_logs', $userId, 25);

$pageTitle = 'User Profile';
$pageDescription = 'Tenant configuration and usage for ' . htmlspecialchars($profileUser->username);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>👤 User Profile: <?php echo htmlspecialchars($profileUser->username); ?></h1>
            <p class="text-muted">Manage tenant API credentials, preferences, subscriptions, and recent usage.</p>
        </div>
        <a class="btn-secondary" href="users.php">← Back to Users</a>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Account</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($profileUser->full_name ?? 'N/A'); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($profileUser->email ?? 'N/A'); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($profileUser->role); ?></p>
            <p><strong>Status:</strong> <?php echo $profileUser->is_active ? 'Active' : 'Inactive'; ?></p>
            <p><strong>Last Login:</strong> <?php echo $profileUser->last_login_at ? date('M d, Y H:i', strtotime($profileUser->last_login_at)) : 'Never'; ?></p>
        </div>

        <div class="card">
            <h3>API Credentials (user_settings)</h3>
            <?php if ($userSettings): ?>
                <ul class="kv-list">
                    <li><span>API Version</span><span><?php echo htmlspecialchars($userSettings->whatsapp_api_version ?? ''); ?></span></li>
                    <li><span>Phone Number ID</span><span><?php echo htmlspecialchars($userSettings->whatsapp_phone_number_id ?? ''); ?></span></li>
                    <li><span>Access Token</span><span><?php echo $userSettings->whatsapp_access_token ? '••••••' : 'Not set'; ?></span></li>
                    <li><span>Phone Number</span><span><?php echo htmlspecialchars($userSettings->phone_number ?? ''); ?></span></li>
                    <li><span>Business Name</span><span><?php echo htmlspecialchars($userSettings->business_name ?? ''); ?></span></li>
                    <li><span>Webhook URL</span><span><?php echo htmlspecialchars($userSettings->webhook_url ?? ''); ?></span></li>
                    <li><span>Webhook Verify Token</span><span><?php echo $userSettings->webhook_verify_token ? '••••••' : 'Not set'; ?></span></li>
                    <li><span>Configured</span><span><?php echo !empty($userSettings->is_configured) ? 'Yes' : 'No'; ?></span></li>
                    <li><span>Last Verified</span><span><?php echo $userSettings->last_verified_at ? $userSettings->last_verified_at : 'Never'; ?></span></li>
                </ul>
            <?php else: ?>
                <p class="text-muted">No user_settings row for this user.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Legacy API Credentials (user_api_credentials)</h3>
            <?php if ($userApiCreds): ?>
                <pre class="json-block"><?php echo htmlspecialchars(json_encode($userApiCreds, JSON_PRETTY_PRINT)); ?></pre>
            <?php else: ?>
                <p class="text-muted">No legacy credentials found.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Preferences (user_preferences)</h3>
            <?php if ($userPreferences): ?>
                <pre class="json-block"><?php echo htmlspecialchars(json_encode($userPreferences, JSON_PRETTY_PRINT)); ?></pre>
            <?php else: ?>
                <p class="text-muted">No preferences saved.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Subscription (user_subscriptions)</h3>
            <?php if ($userSubscription): ?>
                <pre class="json-block"><?php echo htmlspecialchars(json_encode($userSubscription, JSON_PRETTY_PRINT)); ?></pre>
            <?php else: ?>
                <p class="text-muted">No subscription record.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Usage Logs (last 25)</h3>
            <?php if (!$userUsageLogs->isEmpty()): ?>
                <pre class="json-block"><?php echo htmlspecialchars(json_encode($userUsageLogs, JSON_PRETTY_PRINT)); ?></pre>
            <?php else: ?>
                <p class="text-muted">No usage logs.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; margin-bottom: 16px; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
.kv-list { list-style: none; padding: 0; margin: 0; }
.kv-list li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
.kv-list li span:first-child { color: #6b7280; }
.json-block { background: #0b1727; color: #e5e7eb; padding: 10px; border-radius: 8px; max-height: 300px; overflow: auto; font-size: 13px; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
