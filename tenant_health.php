<?php
/**
 * Tenant Health Dashboard - Per-tenant observability and monitoring
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Webhook;
use Illuminate\Database\Capsule\Manager as Capsule;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Last 24 hours
$since = date('Y-m-d H:i:s', time() - 86400);

// Webhook health (MULTI-TENANT: filter by user)
// Note: webhook_deliveries table doesn't exist yet
$webhookStats = [];

// Job queue status (MULTI-TENANT: filter by user)
$pendingScheduled = Capsule::table('scheduled_messages')
    ->where('user_id', $user->id)
    ->where('status', 'pending')
    ->where('scheduled_at', '<=', date('Y-m-d H:i:s'))
    ->count();

$pendingBroadcasts = Capsule::table('broadcasts')
    ->where('user_id', $user->id)
    ->where('status', 'scheduled')
    ->count();

// Error log summary (MULTI-TENANT: filter by user logs)
$errorCount = 0;
$warningCount = 0;
$logDir = __DIR__ . '/storage/logs';
if (is_dir($logDir)) {
    $logs = scandir($logDir);
    foreach ($logs as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'log') {
            $content = file_get_contents($logDir . '/' . $file);
            if (strpos($content, 'user_id":"' . $user->id) !== false) {
                $errorCount += substr_count($content, '"level":"error"');
                $warningCount += substr_count($content, '"level":"warning"');
            }
        }
    }
}

// Message counter
[$messagesSent, $messageLimit] = getUserMessageCounters($user->id);

// Workflow/Drip execution health
$failedWorkflows = Capsule::table('workflow_executions as we')
    ->join('workflows as w', 'we.workflow_id', '=', 'w.id')
    ->where('w.user_id', $user->id)
    ->where('we.executed_at', '>=', $since)
    ->where('we.status', 'failed')
    ->count();

$failedDrips = Capsule::table('drip_subscribers as ds')
    ->join('drip_campaigns as dc', 'ds.campaign_id', '=', 'dc.id')
    ->where('dc.user_id', $user->id)
    ->where('ds.updated_at', '>=', $since)
    ->where('ds.status', 'failed')
    ->count();

$pageTitle = 'Tenant Health Dashboard';
require_once __DIR__ . '/includes/header.php';

/**
 * Helper to get per-tenant message counters
 */
function getUserMessageCounters($userId) {
    $sentKey = 'messages_sent_count_user_' . $userId;
    $limitKey = 'message_limit_user_' . $userId;
    
    $sent = Capsule::table('config')->where('config_key', $sentKey)->value('config_value') ?? 0;
    $limit = Capsule::table('config')->where('config_key', $limitKey)->value('config_value') ?? 500;
    
    return [(int)$sent, (int)$limit];
}
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>🏥 Tenant Health Dashboard</h1>
            <p>System health, quota usage, and error tracking for your tenant</p>
        </div>
        <small class="text-muted">Last 24 hours • Auto-refreshes every 30s</small>
    </div>

    <!-- Key Health Metrics -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Messages Sent</h6>
                    <h3><?php echo number_format($messagesSent); ?> / <?php echo number_format($messageLimit); ?></h3>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($messagesSent / $messageLimit) * 100; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo round(($messagesSent / $messageLimit) * 100, 1); ?>% used</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Webhook Health</h6>
                    <h3><?php echo count($webhookStats); ?> active</h3>
                    <?php
                    $totalWebhookAttempts = 0;
                    $successfulWebhooks = 0;
                    foreach ($webhookStats as $w) {
                        $totalWebhookAttempts += $w->total;
                        $successfulWebhooks += $w->successful;
                    }
                    $webhookSuccessRate = $totalWebhookAttempts > 0 ? round(($successfulWebhooks / $totalWebhookAttempts) * 100, 1) : 100;
                    ?>
                    <small class="text-muted"><?php echo $webhookSuccessRate; ?>% success rate</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Pending Jobs</h6>
                    <h3><?php echo $pendingScheduled + $pendingBroadcasts; ?></h3>
                    <small class="text-muted"><?php echo $pendingScheduled; ?> msgs, <?php echo $pendingBroadcasts; ?> broadcasts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?php echo $errorCount > 0 ? 'border-danger' : ''; ?>">
                <div class="card-body">
                    <h6 class="card-title">Errors (24h)</h6>
                    <h3><?php echo $errorCount; ?></h3>
                    <small class="text-muted"><?php echo $warningCount; ?> warnings</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhook Details -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>🔗 Webhook Status</h5>
        </div>
        <div class="card-body">
            <?php if (empty($webhookStats)): ?>
                <p class="text-muted mb-0">No webhooks configured.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Webhook</th>
                                <th>Attempts (24h)</th>
                                <th>Success Rate</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhookStats as $w): ?>
                                <?php $rate = $w->total > 0 ? ($w->successful / $w->total) * 100 : 100; ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($w->name); ?></strong></td>
                                    <td><?php echo number_format($w->total); ?></td>
                                    <td>
                                        <span class="badge <?php echo $rate >= 95 ? 'bg-success' : ($rate >= 80 ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo round($rate, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo number_format($w->successful); ?>/<?php echo number_format($w->total); ?> delivered</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Failed Automation -->
    <?php if ($failedWorkflows > 0 || $failedDrips > 0): ?>
        <div class="card mb-3 border-danger">
            <div class="card-header bg-light">
                <h5>⚠️ Failed Automations (24h)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Workflow Executions:</strong> <span class="badge bg-danger"><?php echo $failedWorkflows; ?> failed</span></p>
                        <p><a href="execution_logs.php?type=workflow&status=failed">View failed workflows →</a></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Drip Campaigns:</strong> <span class="badge bg-danger"><?php echo $failedDrips; ?> failed</span></p>
                        <p><a href="execution_logs.php?type=drip&status=failed">View failed drips →</a></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="card">
        <div class="card-header">
            <h5>🔧 Actions</h5>
        </div>
        <div class="card-body">
            <p><a href="execution_logs.php" class="btn btn-sm btn-outline-primary">View Execution Logs</a></p>
            <p><a href="webhook-manager.php" class="btn btn-sm btn-outline-primary">Manage Webhooks</a></p>
            <p><small class="text-muted">Dashboard auto-refreshes every 30 seconds.</small></p>
        </div>
    </div>
</div>

<script>
    // Auto-refresh every 30 seconds
    setInterval(() => location.reload(), 30000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
