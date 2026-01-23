<?php
/**
 * CLI smoke test for subscription feature gating.
 * Usage: php smoke_features.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Capsule\Manager as Capsule;

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI only\n";
    exit(1);
}

// Ensure sessions are available for hasFeature/getCurrentUser
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$planFeatures = [
    'free' => ['mailbox', 'quick_replies', 'auto_reply'],
    'starter' => ['mailbox', 'quick_replies', 'broadcasts', 'segments', 'auto_reply', 'tags', 'notes'],
    'pro' => ['mailbox', 'quick_replies', 'broadcasts', 'segments', 'drip_campaigns', 'scheduled_messages', 'auto_reply', 'tags', 'notes', 'message_templates', 'crm', 'analytics'],
    'enterprise' => ['mailbox', 'quick_replies', 'broadcasts', 'segments', 'drip_campaigns', 'scheduled_messages', 'auto_reply', 'tags', 'notes', 'message_templates', 'crm', 'analytics', 'workflows', 'dcmb_ip_commands']
];

$allFeatures = array_values(array_unique(array_merge(...array_values($planFeatures))));

function section($title) {
    echo "\n=== {$title} ===\n";
}

function resultLine($label, $value) {
    echo str_pad($label, 22) . $value . "\n";
}

section('Feature Matrix Smoke');

foreach ($planFeatures as $plan => $expectedFeatures) {
    $username = 'smoke_' . $plan . '_' . uniqid();
    $user = User::create([
        'username' => $username,
        'password' => password_hash('test1234', PASSWORD_DEFAULT),
        'role' => 'user',
        'is_active' => true
    ]);

    $defaults = getDefaultFeaturesForPlan($plan);

    $sub = UserSubscription::create([
        'user_id' => $user->id,
        'plan' => $plan,
        'status' => 'active',
        'message_limit' => 100,
        'messages_used' => 0,
        'contact_limit' => 50,
        'features' => $defaults,
        'current_period_start' => date('Y-m-d H:i:s'),
        'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ]);

    // Impersonate user for hasFeature/requireFeature
    $_SESSION['user_id'] = $user->id;
    $_SESSION['username'] = $user->username;

    $missing = [];
    $unexpected = [];

    foreach ($expectedFeatures as $feat) {
        if (!hasFeature($feat)) {
            $missing[] = $feat;
        }
    }
    foreach ($allFeatures as $feat) {
        $isExpected = in_array($feat, $expectedFeatures, true);
        if (!$isExpected && hasFeature($feat)) {
            $unexpected[] = $feat;
        }
    }

    $status = (empty($missing) && empty($unexpected)) ? 'PASS' : 'FAIL';
    resultLine("Plan {$plan}", $status);

    if (!empty($missing)) {
        resultLine('  missing', implode(', ', $missing));
    }
    if (!empty($unexpected)) {
        resultLine('  unexpected', implode(', ', $unexpected));
    }

    // Cleanup
    UserSubscription::where('id', $sub->id)->delete();
    User::where('id', $user->id)->delete();
    session_unset();
}

echo "\nDone.\n";
