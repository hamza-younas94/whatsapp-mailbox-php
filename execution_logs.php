<?php
/**
 * Execution Logs Page - View workflow/drip/webhook execution history per tenant
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Workflow;
use App\Models\DripCampaign;
use App\Models\DripSubscriber;
use App\Models\Webhook;
use Illuminate\Database\Capsule\Manager as Capsule;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Filter type
$type = sanitize($_GET['type'] ?? 'all'); // all, workflow, drip, webhook
$resourceId = isset($_GET['resource_id']) ? (int)$_GET['resource_id'] : null;
$status = sanitize($_GET['status'] ?? 'all'); // all, success, failed
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Build queries by type
$logs = [];
$typeLabel = 'All Logs';

if ($type === 'workflow' || $type === 'all') {
    // Workflow executions (MULTI-TENANT: filter by user)
    $workflowQuery = Capsule::table('workflow_executions as we')
        ->join('workflows as w', 'we.workflow_id', '=', 'w.id')
        ->where('w.user_id', $user->id)
        ->whereBetween('we.executed_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
        ->select('we.*', 'w.name as workflow_name');
    
    if ($resourceId) {
        $workflowQuery->where('w.id', $resourceId);
    }
    if ($status !== 'all') {
        $workflowQuery->where('we.status', $status);
    }
    
    $workflowLogs = $workflowQuery->orderBy('we.executed_at', 'desc')->limit(1000)->get();
    foreach ($workflowLogs as $log) {
        $logs[] = [
            'type' => 'workflow',
            'resource_name' => $log->workflow_name ?? 'Unknown',
            'resource_id' => $log->workflow_id ?? null,
            'timestamp' => $log->executed_at ?? null,
            'status' => $log->status ?? 'unknown',
            'data' => json_decode($log->actions_performed ?? '{}', true),
            'contact_id' => $log->contact_id ?? null,
            'log' => $log
        ];
    }
    $typeLabel = 'Workflow Executions';
}

if ($type === 'drip' || $type === 'all') {
    // Drip sends (MULTI-TENANT: filter by user)
    $dripQuery = Capsule::table('drip_subscribers as ds')
        ->join('drip_campaigns as dc', 'ds.campaign_id', '=', 'dc.id')
        ->where('dc.user_id', $user->id)
        ->whereBetween('ds.updated_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
        ->select('ds.*', 'dc.name as campaign_name');
    
    if ($resourceId) {
        $dripQuery->where('dc.id', $resourceId);
    }
    if ($status !== 'all') {
        // Map status: completed/failed/pending
        if ($status === 'success') {
            $dripQuery->where('ds.status', 'completed');
        } else {
            $dripQuery->where('ds.status', $status);
        }
    }
    
    $dripLogs = $dripQuery->orderBy('ds.updated_at', 'desc')->limit(1000)->get();
    foreach ($dripLogs as $log) {
        $logs[] = [
            'type' => 'drip',
            'resource_name' => $log->campaign_name ?? 'Unknown',
            'resource_id' => $log->campaign_id ?? null,
            'timestamp' => $log->updated_at ?? null,
            'status' => $log->status ?? 'unknown',
            'data' => ['steps_completed' => $log->completed_steps ?? 0, 'current_step' => $log->current_step ?? 0],
            'contact_id' => $log->contact_id ?? null,
            'log' => $log
        ];
    }
    $typeLabel = 'Drip Campaign Activity';
}

if ($type === 'webhook' || $type === 'all') {
    // Webhook deliveries (MULTI-TENANT: filter by user)
    // Check if table exists first
    try {
        $webhookQuery = Capsule::table('webhook_deliveries as wd')
            ->join('webhooks as wh', 'wd.webhook_id', '=', 'wh.id')
            ->where('wh.user_id', $user->id)
            ->whereBetween('wd.attempted_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('wd.*', 'wh.name as webhook_name', 'wh.url as webhook_url');
        
        if ($resourceId) {
            $webhookQuery->where('wh.id', $resourceId);
        }
        if ($status !== 'all') {
            // Map status: success=delivered, failed=failed
            if ($status === 'success') {
                $webhookQuery->where('wd.status_code', '>=', 200)->where('wd.status_code', '<', 300);
            } else {
                $webhookQuery->where('wd.status_code', '>=', 400);
            }
        }
        
        $webhookLogs = $webhookQuery->orderBy('wd.attempted_at', 'desc')->limit(1000)->get();
        $logs = array_merge($logs, array_map(function($log) {
            return [
                'type' => 'webhook',
                'resource_name' => $log->webhook_name ?? 'Unknown',
                'resource_id' => $log->webhook_id ?? null,
                'timestamp' => $log->attempted_at ?? null,
                'status' => ($log->status_code ?? 0) >= 200 && ($log->status_code ?? 0) < 300 ? 'success' : 'failed',
                'data' => ['status_code' => $log->status_code ?? null, 'response_body' => substr($log->response_body ?? '', 0, 100), 'retry_count' => $log->retry_count ?? 0],
                'contact_id' => null,
                'log' => $log
            ];
        }, (array)$webhookLogs));
        $typeLabel = 'Webhook Deliveries';
    } catch (\Exception $e) {
        // Webhook table doesn't exist, skip
        error_log('Webhook deliveries table check failed: ' . $e->getMessage());
    }
}

// Sort by timestamp descending
usort($logs, function($a, $b) {
    return strtotime($b['timestamp']) - strtotime($a['timestamp']);
});

// Pagination
$perPage = 50;
$total = count($logs);
$pages = ceil($total / $perPage);
$currentPage = (int)($_GET['page'] ?? 1);
$currentPage = max(1, min($currentPage, $pages));
$offset = ($currentPage - 1) * $perPage;
$paginated = array_slice($logs, $offset, $perPage);

// Stats
$stats = [
    'total' => $total,
    'success' => count(array_filter($logs, fn($l) => $l['status'] === 'success' || ($l['status'] >= 200 && $l['status'] < 300))),
    'failed' => count(array_filter($logs, fn($l) => in_array($l['status'], ['failed', 'error']) || ($l['status'] >= 400)))
];

$pageTitle = 'Execution Logs';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>📋 Execution & Delivery Logs</h1>
            <p>Track workflow executions, drip sends, and webhook deliveries</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-auto">
                    <label class="form-label small">Type</label>
                    <select class="form-select form-select-sm" name="type">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="workflow" <?php echo $type === 'workflow' ? 'selected' : ''; ?>>Workflows</option>
                        <option value="drip" <?php echo $type === 'drip' ? 'selected' : ''; ?>>Drip Campaigns</option>
                        <option value="webhook" <?php echo $type === 'webhook' ? 'selected' : ''; ?>>Webhooks</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small">Status</label>
                    <select class="form-select form-select-sm" name="status">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small">From</label>
                    <input type="date" class="form-control form-control-sm" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small">To</label>
                    <input type="date" class="form-control form-control-sm" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6>Total Logs</h6>
                    <h3><?php echo number_format($stats['total']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6>Successful</h6>
                    <h3><?php echo number_format($stats['success']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h6>Failed</h6>
                    <h3><?php echo number_format($stats['failed']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary">
                <div class="card-body">
                    <h6>Success Rate</h6>
                    <h3><?php echo $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0; ?>%</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h5><?php echo htmlspecialchars($typeLabel); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($paginated)): ?>
                <p class="text-muted mb-0">No logs found for the selected criteria.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Type</th>
                                <th>Resource</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginated as $log): ?>
                                <tr>
                                    <td>
                                        <small><?php echo date('M d, Y H:i:s', strtotime($log['timestamp'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $log['type'] === 'workflow' ? 'bg-info' : ($log['type'] === 'drip' ? 'bg-primary' : 'bg-secondary');
                                        ?>">
                                            <?php echo ucfirst($log['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['resource_name']); ?></strong>
                                        <?php if ($log['contact_id']): ?>
                                            <br><small class="text-muted">Contact #<?php echo $log['contact_id']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] === 'success' || ($log['status'] >= 200 && $log['status'] < 300)): ?>
                                            <span class="badge bg-success">✓ Success</span>
                                        <?php elseif ($log['status'] === 'completed'): ?>
                                            <span class="badge bg-success">✓ Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">✗ Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-xs btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#log-<?php echo $log['log']->id ?? $log['resource_id']; ?>">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                                <tr class="collapse" id="log-<?php echo $log['log']->id ?? $log['resource_id']; ?>">
                                    <td colspan="5">
                                        <pre style="font-size: 11px; margin-bottom: 0;"><?php echo json_encode($log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?type=<?php echo $type; ?>&status=<?php echo $status; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
