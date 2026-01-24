<?php
/**
 * Quick Replies Management Page
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\Contact;
use App\Middleware\TenantMiddleware;
use App\Validation;
use Illuminate\Database\Capsule\Manager as Capsule;

// Check if user is authenticated
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

requireFeature('quick_replies');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
            case 'update':
                // Validate input using new Validation class
                $input = [
                    'shortcut' => $_POST['shortcut'] ?? '',
                    'title' => $_POST['title'] ?? '',
                    'message' => $_POST['message'] ?? ''
                ];
                
                $validator = new Validation($input);
                if (!$validator->validate([
                    'shortcut' => 'required|min:1|max:50',
                    'title' => 'required|min:2|max:100',
                    'message' => 'required|min:1|max:4096'
                ])) {
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Validation failed',
                        'errors' => $validator->errors()
                    ]);
                    exit;
                }
                
                // Sanitize all input
                $data = [
                    'shortcut' => Validation::sanitize($_POST['shortcut'] ?? ''),
                    'title' => Validation::sanitize($_POST['title'] ?? ''),
                    'message' => Validation::sanitize($_POST['message'] ?? ''),
                    'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1',
                    // Priority
                    'priority' => (int)($_POST['priority'] ?? 0),
                    // Multiple shortcuts
                    'shortcuts' => !empty($_POST['shortcuts']) ? json_decode($_POST['shortcuts'], true) : null,
                    // Regex
                    'use_regex' => isset($_POST['use_regex']) && $_POST['use_regex'] == '1',
                    // Business hours
                    'business_hours_start' => !empty($_POST['business_hours_start']) ? $_POST['business_hours_start'] : null,
                    'business_hours_end' => !empty($_POST['business_hours_end']) ? $_POST['business_hours_end'] : null,
                    'timezone' => Validation::sanitize($_POST['timezone'] ?? 'UTC'),
                    'outside_hours_message' => Validation::sanitize($_POST['outside_hours_message'] ?? ''),
                    // Conditions
                    'conditions' => !empty($_POST['conditions']) ? json_decode($_POST['conditions'], true) : null,
                    // Delay
                    'delay_seconds' => (int)($_POST['delay_seconds'] ?? 0),
                    // Media
                    'media_url' => Validation::sanitize($_POST['media_url'] ?? ''),
                    'media_type' => Validation::sanitize($_POST['media_type'] ?? ''),
                    'media_filename' => Validation::sanitize($_POST['media_filename'] ?? ''),
                    // Contact filtering
                    'excluded_contact_ids' => !empty($_POST['excluded_contact_ids']) ? json_decode($_POST['excluded_contact_ids'], true) : null,
                    'included_contact_ids' => !empty($_POST['included_contact_ids']) ? json_decode($_POST['included_contact_ids'], true) : null,
                    // Sequences
                    'sequence_messages' => !empty($_POST['sequence_messages']) ? json_decode($_POST['sequence_messages'], true) : null,
                    'sequence_delay_seconds' => (int)($_POST['sequence_delay_seconds'] ?? 2),
                    // Groups
                    'allow_groups' => isset($_POST['allow_groups']) && $_POST['allow_groups'] == '1'
                ];
                
                if ($action === 'create') {
                    $data['created_by'] = $user->id;
                    $data['user_id'] = $user->id; // MULTI-TENANT: Add tenant (user) ID
                    $reply = QuickReply::create($data);
                    echo json_encode(['success' => true, 'reply' => $reply]);
                } else {
                    $reply = QuickReply::findOrFail($_POST['id']);
                    // Verify user owns this reply
                    if (!TenantMiddleware::canAccess($reply, $user->id)) {
                        throw new Exception('Access denied');
                    }
                    $reply->update($data);
                    echo json_encode(['success' => true, 'reply' => $reply]);
                }
                break;
            
            case 'delete':
                $reply = QuickReply::findOrFail($_POST['id']);
                // Verify user owns this reply
                if (!TenantMiddleware::canAccess($reply, $user->id)) {
                    throw new Exception('Access denied');
                }
                $reply->delete();
                echo json_encode(['success' => true]);
                break;
            
            case 'toggle':
                $reply = QuickReply::findOrFail($_POST['id']);
                // Verify user owns this reply
                if (!TenantMiddleware::canAccess($reply, $user->id)) {
                    throw new Exception('Access denied');
                }
                $reply->update(['is_active' => !$reply->is_active]);
                echo json_encode(['success' => true, 'is_active' => $reply->is_active]);
                break;
            
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch all quick replies for current user (MULTI-TENANT)
// Check if priority column exists before ordering by it (for backward compatibility)
$hasPriorityColumn = Capsule::schema()->hasColumn('quick_replies', 'priority');
$query = QuickReply::with('creator')
    ->where('user_id', $user->id); // MULTI-TENANT: Filter by current user

if ($hasPriorityColumn) {
    $query->orderBy('priority', 'desc');
}
$query->orderBy('usage_count', 'desc');

$replies = $query->get();

// Fetch tags and contacts for current user only (MULTI-TENANT)
$tags = Tag::where('user_id', $user->id)->orderBy('name')->get();
$contacts = Contact::where('user_id', $user->id)->orderBy('name')->limit(1000)->get(); // Limit for performance

// Render page
$pageTitle = 'Quick Replies';
$pageDescription = 'Manage canned responses for faster messaging';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.bg-purple {
    background-color: #9333ea !important;
    color: white !important;
}
.message-preview {
    max-width: 300px;
    word-wrap: break-word;
}

/* Tab Styling */
#replyTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
    transition: all 0.3s ease;
    background: transparent;
}

#replyTabs .nav-link:hover {
    background-color: #f8f9fa;
    border-bottom-color: #128C7E;
    color: #128C7E !important;
}

#replyTabs .nav-link.active {
    color: #128C7E !important;
    font-weight: 600;
    border-bottom-color: #128C7E;
    background-color: #f0fdfa;
}

#replyTabs .nav-link i {
    margin-right: 6px;
}

.tab-content {
    padding: 20px 0;
}

.tab-pane {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>⚡ Quick Replies</h1>
            <p>Save time with pre-written message templates</p>
        </div>
        <button class="btn btn-primary" onclick="openReplyModal()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            New Quick Reply
        </button>
    </div>

    <!-- Advanced Features Overview -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-rocket"></i> 🚀 Advanced Auto-Reply Features Available
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-3">Your Quick Replies system now supports <strong>12 advanced features</strong> to create powerful, intelligent auto-replies:</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">1</span>
                                <div>
                                    <strong>Priority/Ordering</strong>
                                    <br><small class="text-muted">Control match order with priority field</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">2</span>
                                <div>
                                    <strong>Business Hours</strong>
                                    <br><small class="text-muted">Time-based replies with timezone support</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">3</span>
                                <div>
                                    <strong>Conditional Replies</strong>
                                    <br><small class="text-muted">Tags, stage, metadata, message count conditions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">4</span>
                                <div>
                                    <strong>Multiple Shortcuts</strong>
                                    <br><small class="text-muted">Link multiple keywords to same response</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">5</span>
                                <div>
                                    <strong>Regex Patterns</strong>
                                    <br><small class="text-muted">Advanced pattern matching support</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">6</span>
                                <div>
                                    <strong>Reply Delays</strong>
                                    <br><small class="text-muted">Simulate human response time</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">7</span>
                                <div>
                                    <strong>Media Support</strong>
                                    <br><small class="text-muted">Images, documents, video in replies</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">8</span>
                                <div>
                                    <strong>Contact Filtering</strong>
                                    <br><small class="text-muted">Blacklist/whitelist specific contacts</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">9</span>
                                <div>
                                    <strong>Reply Sequences</strong>
                                    <br><small class="text-muted">Multi-message sequences with delays</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">10</span>
                                <div>
                                    <strong>Advanced Variables</strong>
                                    <br><small class="text-muted">{{date}}, {{time}}, {{company}}, {{stage}}, etc.</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">11</span>
                                <div>
                                    <strong>Enhanced Analytics</strong>
                                    <br><small class="text-muted">Success/failure tracking, last used date</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <div class="d-flex align-items-start gap-2">
                                <span class="badge bg-primary">12</span>
                                <div>
                                    <strong>Group Support</strong>
                                    <br><small class="text-muted">Works in group messages too</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Click "New Quick Reply" or edit any existing reply to access all these features in the modal tabs!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fuzzy Matching Info Banner -->
    <div class="alert alert-info d-flex align-items-start mb-4" style="border-left: 4px solid #3b82f6;">
        <i class="fas fa-magic" style="font-size: 24px; margin-right: 15px; margin-top: 3px;"></i>
        <div>
            <h5 class="alert-heading mb-2">
                <i class="fas fa-sparkles"></i> Smart Fuzzy Matching Enabled
            </h5>
            <p class="mb-2">
                Quick replies now trigger automatically when the shortcut keyword appears <strong>anywhere</strong> in the customer's message!
            </p>
            <div class="d-flex gap-3 flex-wrap">
                <div class="badge bg-light text-dark px-3 py-2" style="font-size: 13px;">
                    <strong>Example:</strong> Shortcut <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">/hello</code> triggers on:
                    <br><small class="text-muted">✓ "hello" • "hello there" • "say hello" • "HELLO" • "Hello!"</small>
                </div>
                <div class="badge bg-light text-dark px-3 py-2" style="font-size: 13px;">
                    <strong>Example:</strong> Shortcut <code style="background: #e5e7eb; padding: 2px 6px; border-radius: 4px;">/price</code> triggers on:
                    <br><small class="text-muted">✓ "price" • "what's the price" • "show prices" • "pricing info"</small>
                </div>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-info-circle"></i> Works with or without the <code>/</code> prefix and is case-insensitive
            </small>
        </div>
    </div>

    <!-- Quick Replies Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Shortcut(s)</th>
                            <th>Title</th>
                            <th>Message Preview</th>
                            <th>Features</th>
                            <th>Stats</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($replies as $reply): 
                            // Get all shortcuts (legacy + new)
                            $shortcuts = [];
                            if (!empty($reply->shortcuts) && is_array($reply->shortcuts)) {
                                $shortcuts = $reply->shortcuts;
                            }
                            if (!empty($reply->shortcut) && !in_array($reply->shortcut, $shortcuts)) {
                                $shortcuts[] = $reply->shortcut;
                            }
                            if (empty($shortcuts)) {
                                $shortcuts = [$reply->shortcut ?? 'N/A'];
                            }
                            
                            // Check features
                            $hasBusinessHours = !empty($reply->business_hours_start) || !empty($reply->business_hours_end);
                            $hasConditions = !empty($reply->conditions) && is_array($reply->conditions) && count($reply->conditions) > 0;
                            $hasMedia = !empty($reply->media_url) || !empty($reply->media_filename);
                            $hasSequence = !empty($reply->sequence_messages) && is_array($reply->sequence_messages) && count($reply->sequence_messages) > 0;
                            $hasFilters = (!empty($reply->excluded_contact_ids) || !empty($reply->included_contact_ids));
                        ?>
                        <tr>
                            <td>
                                <?php if ($hasPriorityColumn && isset($reply->priority) && $reply->priority > 0): ?>
                                    <span class="badge bg-warning text-dark" title="Priority: <?php echo $reply->priority; ?>">
                                        <i class="fas fa-sort-numeric-down"></i> <?php echo $reply->priority; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" title="Default priority">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <?php foreach ($shortcuts as $sc): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <code class="bg-light px-2 py-1 rounded" style="font-weight: 600; font-size: 11px;">
                                                <?php echo htmlspecialchars($sc); ?>
                                            </code>
                                            <?php if ($reply->use_regex ?? false): ?>
                                                <span class="badge bg-purple" style="font-size: 9px;" title="Uses regex pattern">
                                                    <i class="fas fa-code"></i> Regex
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info" style="font-size: 9px;" title="Fuzzy matching">
                                                    <i class="fas fa-magic"></i> Smart
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($shortcuts) > 1): ?>
                                        <small class="text-muted" style="font-size: 10px;">
                                            <i class="fas fa-list"></i> <?php echo count($shortcuts); ?> shortcuts
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($reply->title); ?></strong>
                                <?php if ($hasSequence): ?>
                                    <br><span class="badge bg-primary" style="font-size: 9px;" title="Message sequence">
                                        <i class="fas fa-list-ol"></i> Sequence
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="message-preview" style="background: #f8f9fa; padding: 8px 12px; border-radius: 8px; border-left: 3px solid #10b981;">
                                    <small style="font-family: -apple-system, system-ui; line-height: 1.4;">
                                        <?php echo nl2br(htmlspecialchars(substr($reply->message, 0, 80)) . (strlen($reply->message) > 80 ? '...' : '')); ?>
                                    </small>
                                </div>
                                <?php if ($hasMedia): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-file-<?php echo $reply->media_type === 'image' ? 'image' : ($reply->media_type === 'video' ? 'video' : 'file'); ?>"></i>
                                        <?php echo ucfirst($reply->media_type ?? 'media'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Count active features
                                $featureCount = 0;
                                $featureList = [];
                                
                                if ($hasBusinessHours) {
                                    $featureCount++;
                                    $featureList[] = 'Business Hours';
                                }
                                if ($hasConditions) {
                                    $featureCount++;
                                    $featureList[] = 'Conditions (' . count($reply->conditions) . ')';
                                }
                                if ($hasFilters) {
                                    $featureCount++;
                                    $featureList[] = 'Contact Filter';
                                }
                                if ($reply->allow_groups ?? false) {
                                    $featureCount++;
                                    $featureList[] = 'Groups';
                                }
                                if (($reply->delay_seconds ?? 0) > 0) {
                                    $featureCount++;
                                    $featureList[] = 'Delay (' . $reply->delay_seconds . 's)';
                                }
                                if ($hasMedia) {
                                    $featureCount++;
                                    $featureList[] = 'Media (' . ucfirst($reply->media_type ?? 'file') . ')';
                                }
                                if ($hasSequence) {
                                    $featureCount++;
                                    $featureList[] = 'Sequence (' . count($reply->sequence_messages) . ' msgs)';
                                }
                                if ($reply->use_regex ?? false) {
                                    $featureCount++;
                                    $featureList[] = 'Regex';
                                }
                                if (($reply->priority ?? 0) > 0) {
                                    $featureCount++;
                                    $featureList[] = 'Priority';
                                }
                                if (!empty($reply->shortcuts) && is_array($reply->shortcuts) && count($reply->shortcuts) > 1) {
                                    $featureCount++;
                                    $featureList[] = 'Multi-Shortcuts';
                                }
                                
                                $hasAdvancedFeatures = $featureCount > 0;
                                ?>
                                
                                <div class="d-flex flex-wrap gap-1" style="max-width: 250px;">
                                    <?php if ($hasBusinessHours): ?>
                                        <span class="badge bg-success" style="font-size: 9px;" title="Business hours: <?php echo date('H:i', strtotime($reply->business_hours_start)); ?> - <?php echo date('H:i', strtotime($reply->business_hours_end)); ?> (<?php echo $reply->timezone; ?>)">
                                            <i class="fas fa-clock"></i> Hours
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasConditions): ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 9px;" title="Conditions: <?php echo count($reply->conditions); ?> rules">
                                            <i class="fas fa-filter"></i> <?php echo count($reply->conditions); ?> Cond
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasFilters): ?>
                                        <span class="badge bg-info" style="font-size: 9px;" title="Contact filtering enabled">
                                            <i class="fas fa-users-slash"></i> Filter
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($reply->allow_groups ?? false): ?>
                                        <span class="badge bg-primary" style="font-size: 9px;" title="Works in group messages">
                                            <i class="fas fa-users"></i> Groups
                                        </span>
                                    <?php endif; ?>
                                    <?php if (($reply->delay_seconds ?? 0) > 0): ?>
                                        <span class="badge bg-secondary" style="font-size: 9px;" title="Reply delay: <?php echo $reply->delay_seconds; ?> seconds">
                                            <i class="fas fa-hourglass-half"></i> <?php echo $reply->delay_seconds; ?>s
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasMedia): ?>
                                        <span class="badge bg-purple" style="font-size: 9px;" title="Media: <?php echo ucfirst($reply->media_type ?? 'file'); ?>">
                                            <i class="fas fa-file-<?php echo $reply->media_type === 'image' ? 'image' : ($reply->media_type === 'video' ? 'video' : 'file'); ?>"></i> Media
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasSequence): ?>
                                        <span class="badge bg-danger" style="font-size: 9px;" title="Message sequence: <?php echo count($reply->sequence_messages); ?> messages">
                                            <i class="fas fa-list-ol"></i> <?php echo count($reply->sequence_messages); ?> Seq
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($reply->use_regex ?? false): ?>
                                        <span class="badge bg-dark" style="font-size: 9px;" title="Uses regex pattern matching">
                                            <i class="fas fa-code"></i> Regex
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($reply->shortcuts) && is_array($reply->shortcuts) && count($reply->shortcuts) > 1): ?>
                                        <span class="badge bg-info" style="font-size: 9px;" title="<?php echo count($reply->shortcuts); ?> shortcuts configured">
                                            <i class="fas fa-list"></i> <?php echo count($reply->shortcuts); ?> Shortcuts
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!$hasAdvancedFeatures): ?>
                                        <span class="badge bg-light text-muted border" style="font-size: 9px;" title="Basic reply - no advanced features configured">
                                            <i class="fas fa-info-circle"></i> Basic
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success" style="font-size: 9px;" title="<?php echo implode(', ', $featureList); ?>">
                                            <i class="fas fa-star"></i> <?php echo $featureCount; ?> Features
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge bg-info" title="Total usage">
                                        <i class="fas fa-chart-line"></i> <?php echo $reply->usage_count ?? 0; ?>
                                    </span>
                                    <?php if ($hasPriorityColumn && isset($reply->success_count) && $reply->success_count > 0): ?>
                                        <span class="badge bg-success" style="font-size: 10px;" title="Success count">
                                            <i class="fas fa-check"></i> <?php echo $reply->success_count; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasPriorityColumn && isset($reply->failure_count) && $reply->failure_count > 0): ?>
                                        <span class="badge bg-danger" style="font-size: 10px;" title="Failure count">
                                            <i class="fas fa-times"></i> <?php echo $reply->failure_count; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($hasPriorityColumn && isset($reply->last_used_at) && $reply->last_used_at): ?>
                                        <small class="text-muted" style="font-size: 9px;" title="Last used: <?php echo date('M d, Y H:i', strtotime($reply->last_used_at)); ?>">
                                            <i class="fas fa-clock"></i> <?php echo date('M d', strtotime($reply->last_used_at)); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           <?php echo $reply->is_active ? 'checked' : ''; ?>
                                           onchange="toggleReply(<?php echo $reply->id; ?>)">
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editReply(<?php echo $reply->id; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteReply(<?php echo $reply->id; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="copyMessage(<?php echo $reply->id; ?>)" title="Copy message">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Quick Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-xl" style="max-width: 900px;">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="replyModalTitle">
                    <i class="fas fa-plus-circle"></i> New Quick Reply
                    <small class="d-block mt-1" style="font-size: 12px; opacity: 0.9;">
                        12 Advanced Features Available • Use tabs below to configure
                    </small>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="replyForm" data-validate='{"shortcut":"required|min:1|max:50","title":"required|min:2|max:100","message":"required|min:1|max:4096"}'>
                    <input type="hidden" id="reply_id" name="id">
                    
                    <!-- Tabs for organization -->
                    <ul class="nav nav-tabs mb-3" id="replyTabs" role="tablist" style="border-bottom: 2px solid #dee2e6;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" style="color: #128C7E; font-weight: 500;">
                                <i class="fas fa-edit"></i> Basic
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" style="color: #6c757d; font-weight: 500;">
                                <i class="fas fa-cogs"></i> Advanced
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="conditions-tab" data-bs-toggle="tab" data-bs-target="#conditions" type="button" style="color: #6c757d; font-weight: 500;">
                                <i class="fas fa-filter"></i> Conditions
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="options-tab" data-bs-toggle="tab" data-bs-target="#options" type="button" style="color: #6c757d; font-weight: 500;">
                                <i class="fas fa-sliders-h"></i> Options
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="replyTabsContent">
                        <!-- Basic Tab -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reply_shortcut" class="form-label">
                                        <i class="fas fa-bolt"></i> Shortcut *
                                        <small class="text-muted">(keyword to trigger)</small>
                                    </label>
                                    <input type="text" class="form-control crm-input" id="reply_shortcut" name="shortcut" placeholder="/hello">
                                    <small class="text-muted">Main shortcut keyword</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reply_priority" class="form-label">
                                        <i class="fas fa-sort-numeric-down"></i> Priority
                                    </label>
                                    <input type="number" class="form-control crm-input" id="reply_priority" name="priority" value="0" min="0">
                                    <small class="text-muted">Higher priority matches first (default: 0)</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reply_shortcuts" class="form-label">
                                    <i class="fas fa-list"></i> Multiple Shortcuts
                                </label>
                                <textarea class="form-control crm-input" id="reply_shortcuts" name="shortcuts" rows="2" placeholder="/hello, /hi, /hey (comma-separated)"></textarea>
                                <small class="text-muted">Additional keywords that trigger this reply (comma-separated)</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="reply_title" class="form-label">
                                        <i class="fas fa-tag"></i> Title *
                                    </label>
                                    <input type="text" class="form-control crm-input" id="reply_title" name="title" placeholder="Welcome Message">
                                    <small class="text-muted">Display name for this quick reply</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="reply_is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="reply_is_active">
                                            Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="reply_message" class="form-label">Message *</label>
                                <textarea class="form-control crm-textarea" id="reply_message" name="message" rows="5" placeholder="Enter your message here..."></textarea>
                                <small class="text-muted">
                                    Tip: Use line breaks for better formatting. 
                                    Variables: {{name}}, {{phone}}, {{message}}, {{date}}, {{time}}, {{company}}, {{stage}}
                                </small>
                            </div>
                        </div>
                        
                        <!-- Advanced Tab -->
                        <div class="tab-pane fade" id="advanced" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Matching Options</label>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="reply_use_regex" name="use_regex" value="1">
                                        <label class="form-check-label" for="reply_use_regex">
                                            Use Regex Pattern
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="reply_allow_groups" name="allow_groups" value="1">
                                        <label class="form-check-label" for="reply_allow_groups">
                                            Allow in Group Messages
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="reply_delay_seconds" class="form-label">
                                        <i class="fas fa-clock"></i> Delay (seconds)
                                    </label>
                                    <input type="number" class="form-control crm-input" id="reply_delay_seconds" name="delay_seconds" value="0" min="0" max="300">
                                    <small class="text-muted">Wait before sending (0 = immediate)</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Business Hours</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="enable_business_hours" onchange="toggleBusinessHours()">
                                    <label class="form-check-label" for="enable_business_hours">
                                        Enable Business Hours Restriction
                                    </label>
                                </div>
                                <div id="business_hours_section" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <input type="time" class="form-control crm-input" id="reply_business_hours_start" name="business_hours_start">
                                            <small class="text-muted">Start Time</small>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <input type="time" class="form-control crm-input" id="reply_business_hours_end" name="business_hours_end">
                                            <small class="text-muted">End Time</small>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <select class="form-control crm-select" id="reply_timezone" name="timezone">
                                                <option value="UTC">UTC</option>
                                                <option value="America/New_York">EST (New York)</option>
                                                <option value="America/Chicago">CST (Chicago)</option>
                                                <option value="America/Denver">MST (Denver)</option>
                                                <option value="America/Los_Angeles">PST (Los Angeles)</option>
                                                <option value="Europe/London">GMT (London)</option>
                                                <option value="Europe/Paris">CET (Paris)</option>
                                                <option value="Asia/Dubai">GST (Dubai)</option>
                                                <option value="Asia/Karachi">PKT (Karachi)</option>
                                                <option value="Asia/Dhaka">BST (Dhaka)</option>
                                                <option value="Asia/Kolkata">IST (India)</option>
                                            </select>
                                            <small class="text-muted">Timezone</small>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <textarea class="form-control crm-textarea" id="reply_outside_hours_message" name="outside_hours_message" rows="2" placeholder="Message to send outside business hours"></textarea>
                                        <small class="text-muted">Outside Hours Message (optional)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Media Support</label>
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <select class="form-control crm-select" id="reply_media_type" name="media_type">
                                            <option value="">None</option>
                                            <option value="image">Image</option>
                                            <option value="document">Document</option>
                                            <option value="video">Video</option>
                                        </select>
                                        <small class="text-muted">Media Type</small>
                                    </div>
                                    <div class="col-md-8 mb-2">
                                        <input type="text" class="form-control crm-input" id="reply_media_url" name="media_url" placeholder="Media URL or file path">
                                        <small class="text-muted">Media URL or filename</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Conditions Tab -->
                        <div class="tab-pane fade" id="conditions" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Conditions (All conditions must be met)</label>
                                <div id="conditions_container">
                                    <div class="alert alert-info">
                                        <small>Add conditions to restrict when this reply triggers. Example: Only for contacts with tag "VIP" or in stage "CUSTOMER"</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addCondition()">
                                    <i class="fas fa-plus"></i> Add Condition
                                </button>
                                <input type="hidden" id="reply_conditions" name="conditions" value="[]">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contact Filtering</label>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Excluded Contacts (Blacklist)</label>
                                        <select class="form-control crm-select" id="reply_excluded_contacts" name="excluded_contact_ids[]" multiple size="5">
                                            <?php foreach ($contacts as $c): ?>
                                            <option value="<?php echo $c->id; ?>"><?php echo htmlspecialchars($c->name . ' (' . $c->phone_number . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Included Contacts (Whitelist)</label>
                                        <select class="form-control crm-select" id="reply_included_contacts" name="included_contact_ids[]" multiple size="5">
                                            <?php foreach ($contacts as $c): ?>
                                            <option value="<?php echo $c->id; ?>"><?php echo htmlspecialchars($c->name . ' (' . $c->phone_number . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">If set, only these contacts will receive this reply</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Options Tab -->
                        <div class="tab-pane fade" id="options" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">
                                    <input type="checkbox" id="enable_sequence" onchange="toggleSequence()"> 
                                    Enable Message Sequence
                                </label>
                                <div id="sequence_section" style="display: none;" class="mt-2">
                                    <textarea class="form-control crm-textarea" id="reply_sequence_messages" name="sequence_messages" rows="6" placeholder='JSON format: [{"message": "First message"}, {"message": "Second message", "media_url": "url", "media_type": "image"}]'></textarea>
                                    <small class="text-muted">
                                        JSON array of messages. Each message can have: message (text), media_url, media_type
                                        <br>Example: [{"message": "Hello!"}, {"message": "How can I help?", "media_type": "image", "media_url": "image.jpg"}]
                                    </small>
                                    <div class="mt-2">
                                        <label for="reply_sequence_delay" class="form-label">Delay Between Messages (seconds)</label>
                                        <input type="number" class="form-control crm-input" id="reply_sequence_delay_seconds" name="sequence_delay_seconds" value="2" min="1">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong><i class="fas fa-info-circle"></i> Advanced Features:</strong>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Priority:</strong> Higher priority replies match first when multiple replies match</li>
                                    <li><strong>Business Hours:</strong> Restrict replies to specific hours with timezone support</li>
                                    <li><strong>Conditions:</strong> Trigger only for contacts with specific tags, stages, or metadata</li>
                                    <li><strong>Sequences:</strong> Send multiple messages in sequence with delays</li>
                                    <li><strong>Variables:</strong> Use {{name}}, {{phone}}, {{date}}, {{time}}, {{company}}, {{stage}} in messages</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveReply()">Save Quick Reply</button>
            </div>
        </div>
    </div>
</div>

<script>
let replyModal;
const repliesData = <?php echo json_encode($replies); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('replyModal');
    if (modalElement) {
        replyModal = new bootstrap.Modal(modalElement);
    }
});

function openReplyModal() {
    if (!replyModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('replyForm').reset();
    document.getElementById('reply_id').value = '';
    document.getElementById('reply_is_active').checked = true;
    document.getElementById('replyModalTitle').textContent = 'New Quick Reply';
    replyModal.show();
}

function editReply(id) {
    if (!replyModal) {
        console.error('Modal not initialized');
        return;
    }
    const reply = repliesData.find(r => r.id === id);
    if (reply) {
        document.getElementById('reply_id').value = reply.id;
        document.getElementById('reply_shortcut').value = reply.shortcut || '';
        document.getElementById('reply_title').value = reply.title || '';
        document.getElementById('reply_message').value = reply.message || '';
        document.getElementById('reply_is_active').checked = reply.is_active !== false;
        document.getElementById('reply_priority').value = reply.priority || 0;
        document.getElementById('reply_shortcuts').value = (reply.shortcuts && Array.isArray(reply.shortcuts)) ? reply.shortcuts.join(', ') : '';
        document.getElementById('reply_use_regex').checked = reply.use_regex === true;
        document.getElementById('reply_allow_groups').checked = reply.allow_groups === true;
        document.getElementById('reply_delay_seconds').value = reply.delay_seconds || 0;
        
        // Business hours
        if (reply.business_hours_start || reply.business_hours_end) {
            document.getElementById('enable_business_hours').checked = true;
            toggleBusinessHours();
            document.getElementById('reply_business_hours_start').value = reply.business_hours_start || '';
            document.getElementById('reply_business_hours_end').value = reply.business_hours_end || '';
            document.getElementById('reply_timezone').value = reply.timezone || 'UTC';
            document.getElementById('reply_outside_hours_message').value = reply.outside_hours_message || '';
        }
        
        // Media
        document.getElementById('reply_media_type').value = reply.media_type || '';
        document.getElementById('reply_media_url').value = reply.media_url || '';
        
        // Conditions
        if (reply.conditions && Array.isArray(reply.conditions)) {
            document.getElementById('reply_conditions').value = JSON.stringify(reply.conditions);
            renderConditions(reply.conditions);
        }
        
        // Contact filtering
        if (reply.excluded_contact_ids && Array.isArray(reply.excluded_contact_ids)) {
            const excludedSelect = document.getElementById('reply_excluded_contacts');
            Array.from(excludedSelect.options).forEach(option => {
                option.selected = reply.excluded_contact_ids.includes(parseInt(option.value));
            });
        }
        if (reply.included_contact_ids && Array.isArray(reply.included_contact_ids)) {
            const includedSelect = document.getElementById('reply_included_contacts');
            Array.from(includedSelect.options).forEach(option => {
                option.selected = reply.included_contact_ids.includes(parseInt(option.value));
            });
        }
        
        // Sequences
        if (reply.sequence_messages && Array.isArray(reply.sequence_messages)) {
            document.getElementById('enable_sequence').checked = true;
            toggleSequence();
            document.getElementById('reply_sequence_messages').value = JSON.stringify(reply.sequence_messages, null, 2);
            document.getElementById('reply_sequence_delay_seconds').value = reply.sequence_delay_seconds || 2;
        }
        
        // Switch to basic tab
        document.getElementById('basic-tab').click();
        
        document.getElementById('replyModalTitle').innerHTML = `
            <i class="fas fa-edit"></i> Edit Quick Reply
            <small class="d-block mt-1" style="font-size: 12px; opacity: 0.9;">
                12 Advanced Features Available • Use tabs below to configure
            </small>
        `;
        replyModal.show();
    }
}

// Initialize reply form validator
let replyValidator;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof FormValidator !== 'undefined') {
        replyValidator = new FormValidator('replyForm', {
            shortcut: ['required', 'min:1', 'max:50'],
            title: ['required', 'min:2', 'max:100'],
            message: ['required', 'min:1', 'max:4096']
        });
    } else {
        console.error('FormValidator is not defined. Make sure validation.js is loaded.');
    }
});

function saveReply() {
    if (!replyValidator || !replyValidator.validate()) {
        return;
    }
    
    const formData = new FormData(document.getElementById('replyForm'));
    const replyId = document.getElementById('reply_id').value;
    
    // Process shortcuts (comma-separated to JSON array)
    const shortcutsText = document.getElementById('reply_shortcuts').value.trim();
    if (shortcutsText) {
        const shortcuts = shortcutsText.split(',').map(s => s.trim()).filter(s => s);
        formData.set('shortcuts', JSON.stringify(shortcuts));
    }
    
    // Process conditions (already JSON string)
    const conditionsValue = document.getElementById('reply_conditions').value;
    if (conditionsValue) {
        formData.set('conditions', conditionsValue);
    }
    
    // Process excluded contacts (multi-select to JSON array)
    const excludedSelect = document.getElementById('reply_excluded_contacts');
    const excludedIds = Array.from(excludedSelect.selectedOptions).map(opt => parseInt(opt.value));
    if (excludedIds.length > 0) {
        formData.set('excluded_contact_ids', JSON.stringify(excludedIds));
    }
    
    // Process included contacts (multi-select to JSON array)
    const includedSelect = document.getElementById('reply_included_contacts');
    const includedIds = Array.from(includedSelect.selectedOptions).map(opt => parseInt(opt.value));
    if (includedIds.length > 0) {
        formData.set('included_contact_ids', JSON.stringify(includedIds));
    }
    
    // Process sequence messages (textarea JSON to JSON string)
    const enableSequence = document.getElementById('enable_sequence').checked;
    if (enableSequence) {
        const sequenceText = document.getElementById('reply_sequence_messages').value.trim();
        if (sequenceText) {
            try {
                const sequence = JSON.parse(sequenceText);
                formData.set('sequence_messages', JSON.stringify(sequence));
            } catch (e) {
                showToast('Invalid JSON in sequence messages: ' + e.message, 'error');
                return;
            }
        }
    }
    
    // Remove business hours if not enabled
    if (!document.getElementById('enable_business_hours').checked) {
        formData.delete('business_hours_start');
        formData.delete('business_hours_end');
        formData.delete('timezone');
        formData.delete('outside_hours_message');
    }
    
    formData.append('action', replyId ? 'update' : 'create');
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Quick reply saved successfully!', 'success');
            replyModal.hide();
            location.reload();
        } else {
            // Handle validation errors from backend
            if (data.errors && replyValidator) {
                replyValidator.setErrors(data.errors);
            } else {
                showToast('Error: ' + (data.error || 'Unknown error'), 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to save quick reply', 'error');
    });
}

function toggleBusinessHours() {
    const enable = document.getElementById('enable_business_hours').checked;
    document.getElementById('business_hours_section').style.display = enable ? 'block' : 'none';
}

function toggleSequence() {
    const enable = document.getElementById('enable_sequence').checked;
    document.getElementById('sequence_section').style.display = enable ? 'block' : 'none';
}

let conditionCounter = 0;
function addCondition() {
    const container = document.getElementById('conditions_container');
    const conditionId = 'condition_' + (++conditionCounter);
    
    const conditionDiv = document.createElement('div');
    conditionDiv.className = 'card mb-2';
    conditionDiv.id = conditionId;
    conditionDiv.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <select class="form-control crm-select condition-field" onchange="updateConditionField(${conditionCounter})">
                        <option value="tag">Tag</option>
                        <option value="stage">Stage</option>
                        <option value="message_count">Message Count</option>
                        <option value="last_message_days">Last Message (days ago)</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select class="form-control crm-select condition-operator">
                        <option value="equals">Equals</option>
                        <option value="not_equals">Not Equals</option>
                        <option value="contains">Contains</option>
                        <option value="greater_than">Greater Than</option>
                        <option value="less_than">Less Than</option>
                        <option value="in">In</option>
                        <option value="not_in">Not In</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <div class="condition-value-container">
                        <input type="text" class="form-control crm-input condition-value" placeholder="Value">
                    </div>
                </div>
                <div class="col-md-1 mb-2">
                    <button type="button" class="btn btn-sm btn-danger" onclick="removeCondition('${conditionId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remove the info alert if it exists
    const alert = container.querySelector('.alert-info');
    if (alert) alert.remove();
    
    container.appendChild(conditionDiv);
    updateConditions();
}

function updateConditionField(counter) {
    // Update value input based on field type (e.g., show dropdown for tags)
    const conditionDiv = document.getElementById('condition_' + counter);
    const fieldSelect = conditionDiv.querySelector('.condition-field');
    const valueContainer = conditionDiv.querySelector('.condition-value-container');
    const field = fieldSelect.value;
    
    if (field === 'tag') {
        valueContainer.innerHTML = `<select class="form-control crm-select condition-value">
            <?php foreach ($tags as $tag): ?>
            <option value="<?php echo htmlspecialchars($tag->name); ?>"><?php echo htmlspecialchars($tag->name); ?></option>
            <?php endforeach; ?>
        </select>`;
    } else if (field === 'stage') {
        valueContainer.innerHTML = `<select class="form-control crm-select condition-value">
            <option value="new">New</option>
            <option value="contacted">Contacted</option>
            <option value="qualified">Qualified</option>
            <option value="proposal">Proposal</option>
            <option value="negotiation">Negotiation</option>
            <option value="customer">Customer</option>
        </select>`;
    } else {
        valueContainer.innerHTML = `<input type="text" class="form-control crm-input condition-value" placeholder="Value">`;
    }
    updateConditions();
}

function removeCondition(conditionId) {
    document.getElementById(conditionId).remove();
    updateConditions();
}

function updateConditions() {
    const conditions = [];
    const conditionDivs = document.querySelectorAll('#conditions_container > .card');
    
    conditionDivs.forEach(div => {
        const field = div.querySelector('.condition-field').value;
        const operator = div.querySelector('.condition-operator').value;
        const value = div.querySelector('.condition-value').value;
        
        if (field && operator && value) {
            conditions.push({
                field: field,
                operator: operator,
                value: value
            });
        }
    });
    
    document.getElementById('reply_conditions').value = JSON.stringify(conditions);
}

function renderConditions(conditions) {
    const container = document.getElementById('conditions_container');
    container.innerHTML = '';
    
    if (!conditions || conditions.length === 0) {
        container.innerHTML = '<div class="alert alert-info"><small>Add conditions to restrict when this reply triggers.</small></div>';
        return;
    }
    
    conditions.forEach((condition, index) => {
        conditionCounter = index + 1;
        const conditionId = 'condition_' + conditionCounter;
        addCondition();
        
        // Set values
        const div = document.getElementById(conditionId);
        if (div) {
            div.querySelector('.condition-field').value = condition.field || 'tag';
            updateConditionField(conditionCounter);
            div.querySelector('.condition-operator').value = condition.operator || 'equals';
            const valueInput = div.querySelector('.condition-value');
            if (valueInput) {
                valueInput.value = condition.value || '';
            }
        }
    });
    
    updateConditions();
}

function openReplyModal() {
    if (!replyModal) {
        console.error('Modal not initialized');
        return;
    }
    document.getElementById('replyForm').reset();
    document.getElementById('reply_id').value = '';
    document.getElementById('reply_is_active').checked = true;
    document.getElementById('reply_priority').value = 0;
    document.getElementById('reply_delay_seconds').value = 0;
    document.getElementById('enable_business_hours').checked = false;
    toggleBusinessHours();
    document.getElementById('enable_sequence').checked = false;
    toggleSequence();
    document.getElementById('conditions_container').innerHTML = '<div class="alert alert-info"><small>Add conditions to restrict when this reply triggers.</small></div>';
    document.getElementById('reply_conditions').value = '[]';
    document.getElementById('replyModalTitle').textContent = 'New Quick Reply';
    
    // Switch to basic tab
    document.getElementById('basic-tab').click();
    
    replyModal.show();
}

function deleteReply(id) {
    if (!confirm('Are you sure you want to delete this quick reply?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Quick reply deleted successfully!', 'success');
            location.reload();
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function toggleReply(id) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    
    fetch('quick-replies.php', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Status updated!', 'success');
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    });
}

function copyMessage(id) {
    const reply = repliesData.find(r => r.id === id);
    if (reply) {
        navigator.clipboard.writeText(reply.message).then(() => {
            showToast('Message copied to clipboard!', 'success');
        });
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
