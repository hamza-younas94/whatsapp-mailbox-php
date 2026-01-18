<?php
/**
 * Test Workflow Execution
 * Manually test workflows with different triggers
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\Contact;
use App\Services\WhatsAppService;

$user = getCurrentUser();
if (!$user || !isAdmin()) {
    die('Access denied. Admin required.');
}

// Get or create test contact
$testContact = Contact::first();
if (!$testContact) {
    die('No contacts found. Please create a contact first.');
}

$whatsappService = new WhatsAppService();

echo "<h1>🧪 Workflow Testing Tool</h1>";
echo "<p>Test workflows with different trigger types</p>";

// Handle test request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test'])) {
    $workflowId = $_POST['workflow_id'] ?? null;
    $triggerType = $_POST['trigger_type'] ?? 'new_message';
    
    if (!$workflowId) {
        die('Workflow ID required');
    }
    
    $workflow = Workflow::find($workflowId);
    if (!$workflow) {
        die('Workflow not found');
    }
    
    echo "<h2>Testing Workflow: {$workflow->name}</h2>";
    echo "<p><strong>Trigger Type:</strong> {$triggerType}</p>";
    echo "<p><strong>Test Contact:</strong> {$testContact->name} ({$testContact->phone_number})</p>";
    
    // Get contact ID from POST if provided
    $contactId = $_POST['contact_id'] ?? $testContact->id;
    $testContact = Contact::find($contactId) ?: $testContact;
    
    try {
        // Prepare context based on trigger type
        $context = [
            'trigger_type' => $triggerType,
            'test_mode' => true
        ];
        
        // Add context data based on trigger type
        switch ($triggerType) {
            case 'new_message':
                $context['message'] = 'Hello test message';
                $context['keyword'] = 'hello';
                break;
            case 'stage_change':
                $context['from_stage'] = 'NEW';
                $context['to_stage'] = 'CONTACTED';
                break;
            case 'tag_added':
            case 'tag_removed':
                $context['tag_id'] = 1; // Use first tag ID
                break;
            case 'lead_score_change':
                $context['lead_score'] = 75;
                break;
        }
        
        // Execute workflow
        $result = $whatsappService->executeWorkflow($workflow, $testContact, $context);
        
        if ($result['success']) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<strong>✅ Workflow Executed Successfully!</strong><br>";
            echo "<strong>Actions Performed:</strong> " . count($result['actions_performed']) . "<br>";
            echo "<strong>Execution ID:</strong> " . ($result['execution_id'] ?? 'N/A');
            echo "</div>";
            
            if (!empty($result['actions_performed'])) {
                echo "<h3>Actions:</h3><ul>";
                foreach ($result['actions_performed'] as $action) {
                    echo "<li>" . htmlspecialchars(json_encode($action, JSON_PRETTY_PRINT)) . "</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<strong>❌ Workflow Failed:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error');
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
    
    echo "<hr><a href='test_workflow.php'>← Back to Test</a>";
    exit;
}

// List workflows
$workflows = Workflow::orderBy('created_at', 'desc')->get();
$contacts = Contact::orderBy('name')->limit(20)->get();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Workflows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 Workflow Testing Tool</h1>
        <p>Manually test workflows with different trigger types</p>
        
        <?php if ($workflows->isEmpty()): ?>
            <div class="alert alert-warning">
                No workflows found. <a href="workflows.php">Create a workflow first</a>.
            </div>
        <?php else: ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h3>Select Workflow to Test</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Workflow</label>
                            <select name="workflow_id" class="form-select" required>
                                <option value="">Select workflow...</option>
                                <?php foreach ($workflows as $wf): ?>
                                    <option value="<?php echo $wf->id; ?>">
                                        <?php echo htmlspecialchars($wf->name); ?> 
                                        (<?php echo ucfirst($wf->trigger_type); ?>)
                                        <?php echo $wf->is_active ? '✅' : '❌'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Trigger Type (for testing)</label>
                            <select name="trigger_type" class="form-select" required>
                                <option value="new_message">New Message</option>
                                <option value="stage_change">Stage Change</option>
                                <option value="tag_added">Tag Added</option>
                                <option value="tag_removed">Tag Removed</option>
                                <option value="lead_score_change">Lead Score Change</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Test Contact</label>
                            <select name="contact_id" class="form-select">
                                <?php foreach ($contacts as $contact): ?>
                                    <option value="<?php echo $contact->id; ?>" <?php echo $contact->id == $testContact->id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($contact->name); ?> (<?php echo htmlspecialchars($contact->phone_number); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" name="test" value="1" class="btn btn-primary">
                            🚀 Test Workflow
                        </button>
                        <a href="workflows.php" class="btn btn-secondary">Back to Workflows</a>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h3>Recent Executions</h3>
                    <?php
                    $executions = WorkflowExecution::with(['workflow', 'contact'])
                        ->orderBy('executed_at', 'desc')
                        ->limit(10)
                        ->get();
                    ?>
                    
                    <?php if ($executions->isEmpty()): ?>
                        <p class="text-muted">No executions yet. Test a workflow to see executions here.</p>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Workflow</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Executed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($executions as $exec): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exec->workflow->name ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($exec->contact->name ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $exec->status === 'success' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($exec->status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $exec->executed_at ? date('M d, Y H:i', strtotime($exec->executed_at)) : 'N/A'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="showExecutionDetails(<?php echo $exec->id; ?>)">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    function showExecutionDetails(id) {
        alert('Execution details for ID: ' + id + '\n\nFull details will be shown in a modal or new page.');
    }
    </script>
</body>
</html>

