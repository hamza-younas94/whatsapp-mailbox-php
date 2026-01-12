<?php
/**
 * Analytics Dashboard
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';

use App\Models\Message;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Broadcast;
use Illuminate\Support\Facades\DB;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Message statistics
$totalMessages = Message::whereBetween('timestamp', [$startDate, $endDate])->count();
$incomingMessages = Message::where('direction', 'incoming')->whereBetween('timestamp', [$startDate, $endDate])->count();
$outgoingMessages = Message::where('direction', 'outgoing')->whereBetween('timestamp', [$startDate, $endDate])->count();
$avgResponseTime = Message::where('direction', 'outgoing')
    ->whereBetween('timestamp', [$startDate, $endDate])
    ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, created_at, timestamp)')) ?? 0;

// Contact statistics
$totalContacts = Contact::count();
$newContacts = Contact::whereBetween('created_at', [$startDate, $endDate])->count();
$activeContacts = Contact::whereBetween('last_message_time', [$startDate, $endDate])->count();

// Deal statistics
$totalRevenue = Deal::where('status', 'won')->whereBetween('deal_date', [$startDate, $endDate])->sum('amount');
$totalDeals = Deal::whereBetween('deal_date', [$startDate, $endDate])->count();
$wonDeals = Deal::where('status', 'won')->whereBetween('deal_date', [$startDate, $endDate])->count();
$conversionRate = $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0;

// Broadcast statistics
$broadcastsSent = Broadcast::where('status', 'completed')->whereBetween('completed_at', [$startDate, $endDate])->count();
$broadcastRecipients = Broadcast::where('status', 'completed')->whereBetween('completed_at', [$startDate, $endDate])->sum('total_recipients');

// Messages by day (for chart)
$messagesByDay = Message::selectRaw('DATE(timestamp) as date, COUNT(*) as count')
    ->whereBetween('timestamp', [$startDate, $endDate])
    ->groupBy('date')
    ->orderBy('date')
    ->get();

// Top contacts by message count
$topContacts = Contact::withCount(['messages' => function($query) use ($startDate, $endDate) {
        $query->whereBetween('timestamp', [$startDate, $endDate]);
    }])
    ->having('messages_count', '>', 0)
    ->orderBy('messages_count', 'desc')
    ->limit(10)
    ->get();

// Stage distribution
$stageDistribution = Contact::selectRaw('stage, COUNT(*) as count')
    ->groupBy('stage')
    ->get();

$pageTitle = 'Analytics Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">📊 Analytics Dashboard</h1>
            <p class="text-muted">Insights and performance metrics</p>
        </div>
        <form method="GET" class="row g-2">
            <div class="col-auto">
                <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
            </div>
            <div class="col-auto">
                <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h6 class="card-title">Total Messages</h6>
                    <h2><?php echo number_format($totalMessages); ?></h2>
                    <small><?php echo $incomingMessages; ?> in / <?php echo $outgoingMessages; ?> out</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h6 class="card-title">Active Contacts</h6>
                    <h2><?php echo number_format($activeContacts); ?></h2>
                    <small><?php echo $newContacts; ?> new contacts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h6 class="card-title">Revenue</h6>
                    <h2>PKR <?php echo number_format($totalRevenue); ?></h2>
                    <small><?php echo $wonDeals; ?> won deals</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h6 class="card-title">Conversion Rate</h6>
                    <h2><?php echo $conversionRate; ?>%</h2>
                    <small><?php echo $wonDeals; ?>/<?php echo $totalDeals; ?> deals</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Messages Chart -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>📈 Messages Over Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="messagesChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Stage Distribution -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>📌 Stage Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="stageChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Contacts -->
    <div class="card">
        <div class="card-header">
            <h5>👥 Top 10 Most Active Contacts</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Contact</th>
                            <th>Phone</th>
                            <th>Messages</th>
                            <th>Stage</th>
                            <th>Lead Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topContacts as $contact): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($contact->name ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($contact->phone_number); ?></td>
                            <td><span class="badge bg-primary"><?php echo $contact->messages_count; ?></span></td>
                            <td><span class="badge bg-info"><?php echo ucfirst($contact->stage); ?></span></td>
                            <td><span class="badge bg-success"><?php echo $contact->lead_score; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Messages over time chart
const messagesData = <?php echo json_encode($messagesByDay); ?>;
const messagesCtx = document.getElementById('messagesChart').getContext('2d');
new Chart(messagesCtx, {
    type: 'line',
    data: {
        labels: messagesData.map(d => d.date),
        datasets: [{
            label: 'Messages',
            data: messagesData.map(d => d.count),
            borderColor: '#25D366',
            backgroundColor: 'rgba(37, 211, 102, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {display: false}
        }
    }
});

// Stage distribution chart
const stageData = <?php echo json_encode($stageDistribution); ?>;
const stageCtx = document.getElementById('stageChart').getContext('2d');
new Chart(stageCtx, {
    type: 'doughnut',
    data: {
        labels: stageData.map(d => d.stage),
        datasets: [{
            data: stageData.map(d => d.count),
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#25D366']
        }]
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
