<?php
/**
 * Seed demo data for a specific tenant (user_id)
 *
 * Usage:
 *   php seed_demo_tenant.php --user=1
 * If --user is omitted, the first user (admin preferred) is used.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\Tag;
use App\Models\Contact;
use App\Models\QuickReply;
use App\Models\Segment;
use App\Models\Workflow;
use App\Models\DripCampaign;
use App\Models\DripCampaignStep;
use App\Models\MessageTemplate;
use App\Models\Webhook;
use Illuminate\Database\Capsule\Manager as Capsule;

// Parse CLI args
$userId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--user=') === 0) {
        $userId = (int) substr($arg, 7);
    }
}

// Pick a user if not provided
if (!$userId) {
    $userId = Capsule::table('users')->where('role', 'admin')->value('id')
        ?? Capsule::table('users')->orderBy('id')->value('id');
}

if (!$userId) {
    echo "❌ No user found. Create a user first.\n";
    exit(1);
}

$user = Capsule::table('users')->where('id', $userId)->first();
if (!$user) {
    echo "❌ User {$userId} not found.\n";
    exit(1);
}

echo "🌱 Seeding demo data for user #{$userId} ({$user->username ?? $user->email})\n";

// Tags
$vipTag = Tag::firstOrCreate([
    'user_id' => $userId,
    'name' => 'VIP',
], [
    'color' => '#f59e0b',
    'description' => 'High-value customers'
]);
$followTag = Tag::firstOrCreate([
    'user_id' => $userId,
    'name' => 'Follow Up',
], [
    'color' => '#3b82f6',
    'description' => 'Needs follow up'
]);

// Contacts
$contactA = Contact::firstOrCreate([
    'user_id' => $userId,
    'phone_number' => '+15550001111'
], [
    'name' => 'Alice Demo',
    'stage' => 'qualified',
    'lead_score' => 78,
    'company_name' => 'DemoCorp',
    'email' => 'alice@example.com',
    'city' => 'NYC'
]);
$contactB = Contact::firstOrCreate([
    'user_id' => $userId,
    'phone_number' => '+15550002222'
], [
    'name' => 'Bob Trial',
    'stage' => 'proposal',
    'lead_score' => 64,
    'company_name' => 'TrialWorks',
    'email' => 'bob@example.com',
    'city' => 'LA'
]);
$contactA->contactTags()->syncWithoutDetaching([$vipTag->id]);
$contactB->contactTags()->syncWithoutDetaching([$followTag->id]);

// Quick replies
QuickReply::firstOrCreate([
    'user_id' => $userId,
    'shortcut' => '/welcome'
], [
    'title' => 'Welcome',
    'message' => 'Hi {{name}}, thanks for reaching out! How can we help today?',
    'is_active' => true
]);
QuickReply::firstOrCreate([
    'user_id' => $userId,
    'shortcut' => '/pricing'
], [
    'title' => 'Pricing',
    'message' => 'Our standard plan starts at $99/mo. Want me to share a proposal?',
    'is_active' => true
]);

// Segment
Segment::firstOrCreate([
    'user_id' => $userId,
    'name' => 'Hot Leads'
], [
    'description' => 'Lead score >= 70 and not yet customer',
    'conditions' => json_encode([
        ['field' => 'lead_score', 'operator' => '>=', 'value' => 70],
        ['field' => 'stage', 'operator' => 'not_in', 'value' => ['customer', 'lost']]
    ]),
    'is_dynamic' => true,
    'contact_count' => 0
]);

// Workflow
Workflow::firstOrCreate([
    'user_id' => $userId,
    'name' => 'Welcome new inbound'
], [
    'description' => 'Auto-tag and reply to new inbound messages containing "hi"',
    'trigger_type' => 'new_message',
    'trigger_conditions' => ['keyword' => 'hi'],
    'actions' => [
        ['type' => 'add_tag', 'tag_id' => $followTag->id],
        ['type' => 'send_message', 'message' => 'Thanks for messaging us! A teammate will reply shortly.']
    ],
    'is_active' => true,
    'created_by' => $userId
]);

// Drip campaign
$drip = DripCampaign::firstOrCreate([
    'user_id' => $userId,
    'name' => 'Onboarding Drip'
], [
    'description' => '3-step onboarding sequence',
    'trigger_conditions' => ['stage' => 'qualified'],
    'is_active' => true,
    'created_by' => $userId
]);
DripCampaignStep::firstOrCreate([
    'campaign_id' => $drip->id,
    'step_number' => 1
], [
    'name' => 'Welcome',
    'delay_minutes' => 0,
    'message_type' => 'text',
    'message_content' => 'Welcome aboard! Here is your quick start guide.'
]);
DripCampaignStep::firstOrCreate([
    'campaign_id' => $drip->id,
    'step_number' => 2
], [
    'name' => 'Nudge',
    'delay_minutes' => 1440,
    'message_type' => 'text',
    'message_content' => 'Have questions? Reply here and we will help.'
]);

// Message template (for outbound template sends)
MessageTemplate::firstOrCreate([
    'user_id' => $userId,
    'name' => 'demo_order_update'
], [
    'whatsapp_template_name' => 'demo_order_update',
    'language_code' => 'en',
    'content' => 'Hi {{1}}, your demo order {{2}} is confirmed.',
    'variables' => [1, 2],
    'category' => 'updates',
    'status' => 'approved',
    'created_by' => $userId
]);

// Webhook (outgoing)
Webhook::firstOrCreate([
    'user_id' => $userId,
    'name' => 'Demo Webhook'
], [
    'url' => 'https://example.com/webhook-demo',
    'events' => ['message.received', 'contact.created'],
    'secret' => bin2hex(random_bytes(8)),
    'is_active' => true
]);

// Sample activities for contacts
$contactA->addActivity('note_added', 'Demo note', 'Seeded note');
$contactB->addActivity('note_added', 'Proposal sent', 'Seeded note');

// Touch timestamps
$contactA->touch('last_activity_at');
$contactB->touch('last_activity_at');

// Summary
$summary = [
    'user_id' => $userId,
    'tags' => Tag::where('user_id', $userId)->count(),
    'contacts' => Contact::where('user_id', $userId)->count(),
    'quick_replies' => QuickReply::where('user_id', $userId)->count(),
    'segments' => Segment::where('user_id', $userId)->count(),
    'workflows' => Workflow::where('user_id', $userId)->count(),
    'drips' => DripCampaign::where('user_id', $userId)->count(),
    'templates' => MessageTemplate::where('user_id', $userId)->count(),
    'webhooks' => Webhook::where('user_id', $userId)->count(),
];

echo "✅ Done. Summary: " . json_encode($summary) . "\n";
