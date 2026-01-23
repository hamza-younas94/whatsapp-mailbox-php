<?php
/**
 * User Profile & Tenant Settings
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\User;
use App\Models\UserSettings;
use App\Models\UserSubscription;

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

// Handle AJAX updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    
    try {
        switch ($action) {
            case 'update_settings':
                $settings = UserSettings::firstOrCreate(['user_id' => $userId]);
                $settings->update([
                    'whatsapp_api_version' => sanitize($_POST['whatsapp_api_version'] ?? 'v18.0'),
                    'whatsapp_access_token' => sanitize($_POST['whatsapp_access_token'] ?? ''),
                    'whatsapp_phone_number_id' => sanitize($_POST['whatsapp_phone_number_id'] ?? ''),
                    'phone_number' => sanitize($_POST['phone_number'] ?? ''),
                    'business_name' => sanitize($_POST['business_name'] ?? ''),
                    'webhook_url' => sanitize($_POST['webhook_url'] ?? ''),
                    'is_configured' => !empty($_POST['whatsapp_access_token']) && !empty($_POST['whatsapp_phone_number_id']),
                    'last_verified_at' => date('Y-m-d H:i:s')
                ]);
                echo json_encode(['success' => true, 'message' => 'Settings updated']);
                break;
            
            case 'update_preferences':
                $prefs = Capsule::table('user_preferences')->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'timezone' => sanitize($_POST['timezone'] ?? 'Asia/Karachi'),
                        'date_format' => sanitize($_POST['date_format'] ?? 'Y-m-d'),
                        'time_format' => sanitize($_POST['time_format'] ?? 'H:i:s'),
                        'language' => sanitize($_POST['language'] ?? 'en'),
                        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                        'browser_notifications' => isset($_POST['browser_notifications']) ? 1 : 0,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
                echo json_encode(['success' => true, 'message' => 'Preferences updated']);
                break;
            
            case 'update_subscription':
                $sub = UserSubscription::firstOrCreate(['user_id' => $userId]);
                
                // Parse features from form checkboxes
                $features = [
                    'mailbox' => isset($_POST['feature_mailbox']),
                    'quick_replies' => isset($_POST['feature_quick_replies']),
                    'broadcasts' => isset($_POST['feature_broadcasts']),
                    'segments' => isset($_POST['feature_segments']),
                    'drip_campaigns' => isset($_POST['feature_drip_campaigns']),
                    'scheduled_messages' => isset($_POST['feature_scheduled_messages']),
                    'auto_reply' => isset($_POST['feature_auto_reply']),
                    'tags' => isset($_POST['feature_tags']),
                    'notes' => isset($_POST['feature_notes']),
                    'message_templates' => isset($_POST['feature_message_templates']),
                    'crm' => isset($_POST['feature_crm']),
                    'analytics' => isset($_POST['feature_analytics']),
                    'workflows' => isset($_POST['feature_workflows']),
                    'dcmb_ip_commands' => isset($_POST['feature_dcmb_ip_commands'])
                ];
                
                $plan = sanitize($_POST['plan'] ?? 'free');
                if ($plan === 'professional') {
                    $plan = 'pro';
                }

                $sub->update([
                    'plan' => $plan,
                    'status' => sanitize($_POST['status'] ?? 'active'),
                    'message_limit' => (int) ($_POST['message_limit'] ?? 1000),
                    'contact_limit' => (int) ($_POST['contact_limit'] ?? 100),
                    'features' => $features,
                    'current_period_start' => $_POST['current_period_start'] ?? date('Y-m-d H:i:s'),
                    'current_period_end' => $_POST['current_period_end'] ?? date('Y-m-d H:i:s', strtotime('+30 days'))
                ]);
                echo json_encode(['success' => true, 'message' => 'Subscription updated']);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
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
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>API Credentials (user_settings)</h3>
                <button class="btn-primary" onclick="editSettings()">✏️ Edit</button>
            </div>
            <?php if ($userSettings): ?>
                <ul class="kv-list" id="settings-view">
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
                <form id="settings-form" style="display: none;">
                    <div class="form-group">
                        <label>API Version</label>
                        <input type="text" name="whatsapp_api_version" value="<?php echo htmlspecialchars($userSettings->whatsapp_api_version ?? 'v18.0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" name="whatsapp_phone_number_id" value="<?php echo htmlspecialchars($userSettings->whatsapp_phone_number_id ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Access Token</label>
                        <input type="text" name="whatsapp_access_token" value="<?php echo htmlspecialchars($userSettings->whatsapp_access_token ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($userSettings->phone_number ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Business Name</label>
                        <input type="text" name="business_name" value="<?php echo htmlspecialchars($userSettings->business_name ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Webhook URL</label>
                        <input type="text" name="webhook_url" value="<?php echo htmlspecialchars($userSettings->webhook_url ?? ''); ?>">
                    </div>
                    <div style="margin-top: 15px;">
                        <button type="button" class="btn-primary" onclick="saveSettings()">💾 Save</button>
                        <button type="button" class="btn-secondary" onclick="cancelEdit('settings')">Cancel</button>
                    </div>
                </form>
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
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Preferences (user_preferences)</h3>
                <button class="btn-primary" onclick="editPreferences()">✏️ Edit</button>
            </div>
            <?php if ($userPreferences): ?>
                <div id="prefs-view">
                    <pre class="json-block"><?php echo htmlspecialchars(json_encode($userPreferences, JSON_PRETTY_PRINT)); ?></pre>
                </div>
                <form id="prefs-form" style="display: none;">
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone">
                            <option value="Asia/Karachi" <?php echo ($userPreferences->timezone ?? '') === 'Asia/Karachi' ? 'selected' : ''; ?>>Asia/Karachi</option>
                            <option value="America/New_York" <?php echo ($userPreferences->timezone ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                            <option value="Europe/London" <?php echo ($userPreferences->timezone ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                            <option value="UTC" <?php echo ($userPreferences->timezone ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Format</label>
                        <select name="date_format">
                            <option value="Y-m-d" <?php echo ($userPreferences->date_format ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>Y-m-d</option>
                            <option value="m/d/Y" <?php echo ($userPreferences->date_format ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>m/d/Y</option>
                            <option value="d-m-Y" <?php echo ($userPreferences->date_format ?? '') === 'd-m-Y' ? 'selected' : ''; ?>>d-m-Y</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time Format</label>
                        <select name="time_format">
                            <option value="H:i:s" <?php echo ($userPreferences->time_format ?? '') === 'H:i:s' ? 'selected' : ''; ?>>24-hour (H:i:s)</option>
                            <option value="g:i A" <?php echo ($userPreferences->time_format ?? '') === 'g:i A' ? 'selected' : ''; ?>>12-hour (g:i A)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Language</label>
                        <input type="text" name="language" value="<?php echo htmlspecialchars($userPreferences->language ?? 'en'); ?>">
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="email_notifications" <?php echo !empty($userPreferences->email_notifications) ? 'checked' : ''; ?>> Email Notifications</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="browser_notifications" <?php echo !empty($userPreferences->browser_notifications) ? 'checked' : ''; ?>> Browser Notifications</label>
                    </div>
                    <div style="margin-top: 15px;">
                        <button type="button" class="btn-primary" onclick="savePreferences()">💾 Save</button>
                        <button type="button" class="btn-secondary" onclick="cancelEdit('prefs')">Cancel</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-muted">No preferences saved.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>Subscription (user_subscriptions)</h3>
                <button class="btn-primary" onclick="editSubscription()">✏️ Edit</button>
            </div>
            <?php if ($userSubscription): ?>
                <div id="sub-view">
                    <ul class="kv-list">
                        <li><span>Plan</span><span><?php echo htmlspecialchars($userSubscription->plan ?? ''); ?></span></li>
                        <li><span>Status</span><span><?php echo htmlspecialchars($userSubscription->status ?? ''); ?></span></li>
                        <li><span>Message Limit</span><span><?php echo number_format($userSubscription->message_limit ?? 0); ?></span></li>
                        <li><span>Messages Used</span><span><?php echo number_format($userSubscription->messages_used ?? 0); ?></span></li>
                        <li><span>Contact Limit</span><span><?php echo number_format($userSubscription->contact_limit ?? 0); ?></span></li>
                        <li><span>Period Start</span><span><?php echo $userSubscription->current_period_start ?? 'N/A'; ?></span></li>
                        <li><span>Period End</span><span><?php echo $userSubscription->current_period_end ?? 'N/A'; ?></span></li>
                        <li><span>Trial Ends</span><span><?php echo $userSubscription->trial_ends_at ?? 'N/A'; ?></span></li>
                    </ul>
                </div>
                <form id="sub-form" style="display: none;">
                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan">
                            <option value="free" <?php echo ($userSubscription->plan ?? '') === 'free' ? 'selected' : ''; ?>>Free (100 msgs)</option>
                            <option value="starter" <?php echo ($userSubscription->plan ?? '') === 'starter' ? 'selected' : ''; ?>>Starter (1,000 msgs)</option>
                            <option value="pro" <?php echo in_array(($userSubscription->plan ?? ''), ['pro','professional']) ? 'selected' : ''; ?>>Professional (10,000 msgs)</option>
                            <option value="enterprise" <?php echo ($userSubscription->plan ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise (Unlimited)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($userSubscription->status ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="trial" <?php echo ($userSubscription->status ?? '') === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="cancelled" <?php echo ($userSubscription->status ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="expired" <?php echo ($userSubscription->status ?? '') === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message Limit</label>
                        <input type="number" name="message_limit" value="<?php echo $userSubscription->message_limit ?? 1000; ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Contact Limit</label>
                        <input type="number" name="contact_limit" value="<?php echo $userSubscription->contact_limit ?? 100; ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Period Start</label>
                        <input type="datetime-local" name="current_period_start" value="<?php echo date('Y-m-d\TH:i', strtotime($userSubscription->current_period_start ?? 'now')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Period End</label>
                        <input type="datetime-local" name="current_period_end" value="<?php echo date('Y-m-d\TH:i', strtotime($userSubscription->current_period_end ?? '+30 days')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 700; margin-top: 15px; display: block;">Feature Access</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px;">
                            <?php 
                            $features = $userSubscription->features ?? [];
                            $featureLabels = [
                                'mailbox' => '📨 Mailbox',
                                'quick_replies' => '⚡ Quick Replies',
                                'broadcasts' => '📢 Broadcasts',
                                'segments' => '🎯 Segments',
                                'drip_campaigns' => '💧 Drip Campaigns',
                                'scheduled_messages' => '⏰ Scheduled Messages',
                                'auto_reply' => '🤖 Auto Reply',
                                'tags' => '🏷️ Tags',
                                'notes' => '📝 Notes',
                                'message_templates' => '📋 Message Templates',
                                'crm' => '👥 CRM',
                                'analytics' => '📊 Analytics',
                                'workflows' => '⚙️ Workflows',
                                'dcmb_ip_commands' => '🌐 DCMB IP Commands'
                            ];
                            foreach ($featureLabels as $key => $label): 
                                $checked = !empty($features[$key]) ? 'checked' : '';
                            ?>
                                <label style="font-weight: normal; font-size: 14px;">
                                    <input type="checkbox" name="feature_<?php echo $key; ?>" <?php echo $checked; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <button type="button" class="btn-primary" onclick="saveSubscription()">💾 Save</button>
                        <button type="button" class="btn-secondary" onclick="cancelEdit('sub')">Cancel</button>
                    </div>
                </form>
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
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-weight: 500; margin-bottom: 4px; font-size: 14px; }
.form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; }
</style>

<script>
const userId = <?php echo $userId; ?>;

function toggleSection(viewId, formId, showForm) {
    const view = document.getElementById(viewId);
    const form = document.getElementById(formId);
    if (!view || !form) {
        return;
    }
    view.style.display = showForm ? 'none' : 'block';
    form.style.display = showForm ? 'block' : 'none';
}

function editSettings() {
    toggleSection('settings-view', 'settings-form', true);
}

function editPreferences() {
    toggleSection('prefs-view', 'prefs-form', true);
}

function editSubscription() {
    toggleSection('sub-view', 'sub-form', true);
}

function cancelEdit(section) {
    if (section === 'settings') {
        toggleSection('settings-view', 'settings-form', false);
    } else if (section === 'prefs') {
        toggleSection('prefs-view', 'prefs-form', false);
    } else if (section === 'sub') {
        toggleSection('sub-view', 'sub-form', false);
    }
}

function saveSettings() {
    const formData = new FormData(document.getElementById('settings-form'));
    formData.append('action', 'update_settings');
    formData.append('user_id', userId);
    
    fetch('user-profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Settings updated successfully');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(e => alert('❌ Request failed: ' + e.message));
}

function savePreferences() {
    const formData = new FormData(document.getElementById('prefs-form'));
    formData.append('action', 'update_preferences');
    formData.append('user_id', userId);
    
    fetch('user-profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Preferences updated successfully');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(e => alert('❌ Request failed: ' + e.message));
}

function saveSubscription() {
    const formData = new FormData(document.getElementById('sub-form'));
    formData.append('action', 'update_subscription');
    formData.append('user_id', userId);
    
    fetch('user-profile.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ Subscription updated successfully');
            location.reload();
        } else {
            alert('❌ Error: ' + data.error);
        }
    })
    .catch(e => alert('❌ Request failed: ' + e.message));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
