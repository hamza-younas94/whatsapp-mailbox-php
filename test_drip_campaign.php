<?php
/**
 * Test Drip Campaign Execution
 * Manually test drip campaigns by adding subscribers and sending steps
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\DripCampaign;
use App\Models\DripSubscriber;
use App\Models\Contact;
use App\Services\WhatsAppService;

$user = getCurrentUser();
if (!$user || !isAdmin()) {
    die('Access denied. Admin required.');
}

$whatsappService = new WhatsAppService();

echo "<h1>💧 Drip Campaign Testing Tool</h1>";

// Handle test request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test'])) {
    $campaignId = $_POST['campaign_id'] ?? null;
    $action = $_POST['action'] ?? '';
    
    if (!$campaignId) {
        die('Campaign ID required');
    }
    
    $campaign = DripCampaign::find($campaignId);
    if (!$campaign) {
        die('Campaign not found');
    }
    
    try {
        if ($action === 'add_subscriber') {
            $contactId = $_POST['contact_id'] ?? null;
            if (!$contactId) {
                die('Contact ID required');
            }
            
            $contact = Contact::find($contactId);
            if (!$contact) {
                die('Contact not found');
            }
            
            // Check if already subscribed
            $existing = DripSubscriber::where('campaign_id', $campaignId)
                ->where('contact_id', $contactId)
                ->first();
            
            if ($existing) {
                echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<strong>⚠️ Contact already subscribed to this campaign</strong><br>";
                echo "Subscriber ID: {$existing->id}<br>";
                echo "Status: {$existing->status}<br>";
                echo "Current Step: {$existing->current_step}";
                echo "</div>";
            } else {
                // Add subscriber
                $subscriber = DripSubscriber::create([
                    'campaign_id' => $campaignId,
                    'contact_id' => $contactId,
                    'current_step' => 0,
                    'status' => 'active',
                    'next_send_at' => now(),
                    'started_at' => now()
                ]);
                
                $campaign->increment('total_subscribers');
                
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<strong>✅ Subscriber Added!</strong><br>";
                echo "Subscriber ID: {$subscriber->id}<br>";
                echo "Next message will be sent immediately (step 1)";
                echo "</div>";
            }
        } elseif ($action === 'send_next_step') {
            // Send next step for a subscriber
            $subscriberId = $_POST['subscriber_id'] ?? null;
            if (!$subscriberId) {
                die('Subscriber ID required');
            }
            
            $subscriber = DripSubscriber::with(['campaign', 'contact'])->find($subscriberId);
            if (!$subscriber) {
                die('Subscriber not found');
            }
            
            // Get or update subscriber
            $subscriberId = $_POST['subscriber_id'] ?? null;
            if ($subscriberId) {
                $subscriber = DripSubscriber::with(['campaign', 'contact'])->find($subscriberId);
                if (!$subscriber) {
                    die('Subscriber not found');
                }
            }
            
            $result = $whatsappService->sendDripCampaignStep($subscriber, true);
            
            if ($result['success']) {
                echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<strong>✅ Step Sent!</strong><br>";
                echo "<strong>Step:</strong> {$result['step_name']}<br>";
                echo "<strong>Message:</strong> " . htmlspecialchars(substr($result['message'], 0, 100)) . "...<br>";
                echo "<strong>Next Send At:</strong> " . ($result['next_send_at'] ?? 'Campaign completed');
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<strong>❌ Failed:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error');
                echo "</div>";
            }
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
    echo "<hr><a href='test_drip_campaign.php'>← Back to Test</a>";
    exit;
}

// List campaigns
$campaigns = DripCampaign::with('steps')->orderBy('created_at', 'desc')->get();
$contacts = Contact::orderBy('name')->limit(50)->get();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Drip Campaigns</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>💧 Drip Campaign Testing Tool</h1>
        <p>Test drip campaigns by adding subscribers and sending steps manually</p>
        
        <?php if ($campaigns->isEmpty()): ?>
            <div class="alert alert-warning">
                No drip campaigns found. <a href="drip-campaigns.php">Create a campaign first</a>.
            </div>
        <?php else: ?>
            <!-- Add Subscriber Form -->
            <div class="card mt-4">
                <div class="card-body">
                    <h3>Add Subscriber to Campaign</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_subscriber">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Campaign</label>
                                <select name="campaign_id" class="form-select" required>
                                    <option value="">Select campaign...</option>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <option value="<?php echo $campaign->id; ?>">
                                            <?php echo htmlspecialchars($campaign->name); ?> 
                                            (<?php echo $campaign->steps->count(); ?> steps)
                                            <?php echo $campaign->is_active ? '✅' : '❌'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact</label>
                                <select name="contact_id" class="form-select" required>
                                    <option value="">Select contact...</option>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?php echo $contact->id; ?>">
                                            <?php echo htmlspecialchars($contact->name); ?> 
                                            (<?php echo htmlspecialchars($contact->phone_number); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="test" value="1" class="btn btn-primary">
                            ➕ Add Subscriber & Start Campaign
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Campaign Subscribers -->
            <div class="card mt-4">
                <div class="card-body">
                    <h3>Active Subscribers</h3>
                    <?php
                    $allSubscribers = DripSubscriber::with(['campaign', 'contact'])
                        ->orderBy('created_at', 'desc')
                        ->limit(50)
                        ->get();
                    ?>
                    
                    <?php if ($allSubscribers->isEmpty()): ?>
                        <p class="text-muted">No subscribers yet. Add a subscriber above to start testing.</p>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Contact</th>
                                    <th>Current Step</th>
                                    <th>Status</th>
                                    <th>Next Send At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allSubscribers as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub->campaign->name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($sub->contact->name ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                Step <?php echo $sub->current_step; ?> / <?php echo $sub->campaign->steps->count(); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $sub->status === 'active' ? 'success' : 
                                                    ($sub->status === 'completed' ? 'primary' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($sub->status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($sub->next_send_at): ?>
                                                <?php echo date('M d, Y H:i', strtotime($sub->next_send_at)); ?>
                                                <?php if (strtotime($sub->next_send_at) <= time()): ?>
                                                    <span class="badge bg-warning">Due Now</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sub->status === 'active' && $sub->next_send_at && strtotime($sub->next_send_at) <= time()): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="send_next_step">
                                                    <input type="hidden" name="campaign_id" value="<?php echo $sub->campaign_id; ?>">
                                                    <input type="hidden" name="subscriber_id" value="<?php echo $sub->id; ?>">
                                                    <button type="submit" name="test" value="1" class="btn btn-sm btn-success">
                                                        📤 Send Next Step
                                                    </button>
                                                </form>
                                            <?php elseif ($sub->status === 'active'): ?>
                                                <span class="text-muted">Waiting for delay</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="drip-campaigns.php" class="btn btn-secondary">Back to Drip Campaigns</a>
        </div>
    </div>
</body>
</html>

