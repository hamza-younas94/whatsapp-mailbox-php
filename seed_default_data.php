<?php
/**
 * Seed Default Data for Workflows, Drip Campaigns, Message Templates, and Webhooks
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\Workflow;
use App\Models\DripCampaign;
use App\Models\DripCampaignStep;
use App\Models\MessageTemplate;
use App\Models\Webhook;
use Illuminate\Database\Capsule\Manager as Capsule;

echo "🌱 Seeding default data...\n\n";

// Get first admin user or create default
$userId = null;
try {
    if (Capsule::schema()->hasTable('users')) {
        $firstUser = Capsule::table('users')->where('role', 'admin')->first();
        if ($firstUser) {
            $userId = $firstUser->id;
        } else {
            // Try any user
            $anyUser = Capsule::table('users')->first();
            if ($anyUser) {
                $userId = $anyUser->id;
            }
        }
    }
    if (!$userId && Capsule::schema()->hasTable('admin_users')) {
        $adminUser = Capsule::table('admin_users')->first();
        if ($adminUser) {
            $userId = $adminUser->id;
        }
    }
} catch (Exception $e) {
    echo "⚠️  Could not find user table: " . $e->getMessage() . "\n";
}

if (!$userId) {
    echo "❌ Error: No users found in database. Please create a user first.\n";
    echo "   You can do this by logging into the system or creating a user via users.php\n";
    exit(1);
}

echo "✅ Using user ID: {$userId}\n\n";

// 1. Seed Workflows
echo "📋 Creating sample workflows...\n";
try {
    if (Capsule::schema()->hasTable('workflows')) {
        $existingCount = Workflow::count();
        if ($existingCount == 0) {
            // Workflow 1: Auto-reply to new messages
            Workflow::create([
                'name' => 'Welcome New Contacts',
                'description' => 'Automatically send welcome message to new contacts',
                'trigger_type' => 'new_message',
                'trigger_conditions' => [
                    'keyword' => 'hello',
                    'tag_ids' => []
                ],
                'actions' => [
                    [
                        'type' => 'send_message',
                        'message' => 'Hello! Thank you for contacting us. How can we help you today?'
                    ],
                    [
                        'type' => 'add_tag',
                        'tag_id' => 1 // Assuming tag ID 1 exists
                    ]
                ],
                'is_active' => true,
                'created_by' => $userId
            ]);
            
            // Workflow 2: Auto-tag based on message
            Workflow::create([
                'name' => 'Tag Interested Contacts',
                'description' => 'Tag contacts who mention interest or pricing',
                'trigger_type' => 'new_message',
                'trigger_conditions' => [
                    'keyword' => 'price',
                    'tag_ids' => []
                ],
                'actions' => [
                    [
                        'type' => 'add_tag',
                        'tag_id' => 2 // Assuming tag ID 2 exists
                    ],
                    [
                        'type' => 'change_stage',
                        'stage' => 'QUALIFIED'
                    ]
                ],
                'is_active' => true,
                'created_by' => $userId
            ]);
            
            // Workflow 3: Follow up on stage change
            Workflow::create([
                'name' => 'Follow Up on Proposal',
                'description' => 'Send follow-up message when stage changes to Proposal',
                'trigger_type' => 'stage_change',
                'trigger_conditions' => [
                    'from_stage' => 'QUALIFIED',
                    'to_stage' => 'PROPOSAL'
                ],
                'actions' => [
                    [
                        'type' => 'send_message',
                        'message' => 'Thank you for your interest! We have sent you a proposal. Please let us know if you have any questions.'
                    ],
                    [
                        'type' => 'create_note',
                        'note' => 'Proposal sent - automated follow-up',
                        'note_type' => 'general'
                    ]
                ],
                'is_active' => false, // Inactive by default
                'created_by' => $userId
            ]);
            
            echo "✅ Created 3 sample workflows\n";
        } else {
            echo "ℹ️  Workflows already exist ({$existingCount} workflows)\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error creating workflows: " . $e->getMessage() . "\n";
}

// 2. Seed Drip Campaigns
echo "\n💧 Creating sample drip campaigns...\n";
try {
    if (Capsule::schema()->hasTable('drip_campaigns')) {
        $existingCount = DripCampaign::count();
        if ($existingCount == 0) {
            // Campaign 1: Welcome series
            $campaign1 = DripCampaign::create([
                'name' => 'Welcome Series',
                'description' => '3-step welcome sequence for new customers',
                'is_active' => true,
                'trigger_conditions' => [
                    'stage' => 'NEW'
                ],
                'created_by' => $userId
            ]);
            
            DripCampaignStep::create([
                'campaign_id' => $campaign1->id,
                'step_number' => 1,
                'name' => 'Welcome Message',
                'delay_minutes' => 0,
                'message_type' => 'text',
                'message_content' => 'Welcome to our service! We\'re excited to have you here.'
            ]);
            
            DripCampaignStep::create([
                'campaign_id' => $campaign1->id,
                'step_number' => 2,
                'name' => 'Product Overview',
                'delay_minutes' => 60, // 1 hour
                'message_type' => 'text',
                'message_content' => 'Here\'s a quick overview of our main features and how they can help you.'
            ]);
            
            DripCampaignStep::create([
                'campaign_id' => $campaign1->id,
                'step_number' => 3,
                'name' => 'Get Started',
                'delay_minutes' => 1440, // 24 hours
                'message_type' => 'text',
                'message_content' => 'Ready to get started? Reply to this message and we\'ll help you set up your account!'
            ]);
            
            // Campaign 2: Follow-up series
            $campaign2 = DripCampaign::create([
                'name' => 'Follow-up Reminder',
                'description' => 'Remind contacts who haven\'t responded in 3 days',
                'is_active' => false,
                'trigger_conditions' => [
                    'tags' => [1] // Assuming tag ID 1
                ],
                'created_by' => $userId
            ]);
            
            DripCampaignStep::create([
                'campaign_id' => $campaign2->id,
                'step_number' => 1,
                'name' => 'Friendly Reminder',
                'delay_minutes' => 4320, // 3 days
                'message_type' => 'text',
                'message_content' => 'Hi! Just wanted to check in and see if you had any questions about our service.'
            ]);
            
            echo "✅ Created 2 sample drip campaigns with steps\n";
        } else {
            echo "ℹ️  Drip campaigns already exist ({$existingCount} campaigns)\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error creating drip campaigns: " . $e->getMessage() . "\n";
}

// 3. Seed Message Templates
echo "\n📝 Creating sample message templates...\n";
try {
    if (Capsule::schema()->hasTable('message_templates')) {
        $existingCount = MessageTemplate::count();
        if ($existingCount == 0) {
            MessageTemplate::create([
                'name' => 'Welcome Template',
                'whatsapp_template_name' => 'hello_world',
                'language_code' => 'en',
                'content' => 'Hello {{1}}, welcome to our service!',
                'variables' => [1],
                'category' => 'Welcome',
                'status' => 'approved',
                'created_by' => $userId
            ]);
            
            MessageTemplate::create([
                'name' => 'Order Confirmation',
                'whatsapp_template_name' => 'order_confirm',
                'language_code' => 'en',
                'content' => 'Hi {{1}}, your order {{2}} has been confirmed and will be delivered on {{3}}.',
                'variables' => [1, 2, 3],
                'category' => 'Orders',
                'status' => 'pending',
                'created_by' => $userId
            ]);
            
            MessageTemplate::create([
                'name' => 'Payment Reminder',
                'whatsapp_template_name' => 'payment_reminder',
                'language_code' => 'en',
                'content' => 'Hello {{1}}, this is a reminder that your payment of {{2}} is due on {{3}}.',
                'variables' => [1, 2, 3],
                'category' => 'Billing',
                'status' => 'approved',
                'created_by' => $userId
            ]);
            
            echo "✅ Created 3 sample message templates\n";
        } else {
            echo "ℹ️  Message templates already exist ({$existingCount} templates)\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error creating message templates: " . $e->getMessage() . "\n";
}

// 4. Seed Webhooks
echo "\n🔗 Creating sample webhooks...\n";
try {
    if (Capsule::schema()->hasTable('webhooks')) {
        $existingCount = Webhook::count();
        if ($existingCount == 0) {
            // Webhook 1: Receive all message events
            Webhook::create([
                'name' => 'Message Events Webhook',
                'url' => 'https://your-domain.com/webhook-handler',
                'events' => [
                    'message.received',
                    'message.sent',
                    'message.delivered',
                    'message.read',
                    'message.failed'
                ],
                'secret' => bin2hex(random_bytes(16)),
                'is_active' => false, // Inactive by default
            ]);
            
            // Webhook 2: Contact updates
            Webhook::create([
                'name' => 'Contact Updates',
                'url' => 'https://your-domain.com/contact-webhook',
                'events' => [
                    'contact.created',
                    'contact.updated',
                    'stage.changed',
                    'tag.added',
                    'tag.removed'
                ],
                'secret' => bin2hex(random_bytes(16)),
                'is_active' => false,
            ]);
            
            echo "✅ Created 2 sample webhooks\n";
            echo "⚠️  Note: Webhooks are inactive by default. Update the URLs to your endpoints and activate them.\n";
        } else {
            echo "ℹ️  Webhooks already exist ({$existingCount} webhooks)\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error creating webhooks: " . $e->getMessage() . "\n";
}

echo "\n✨ Seeding complete!\n";
echo "\n💡 Tip: Review and customize the sample data in the admin pages:\n";
echo "   - Workflows: workflows.php\n";
echo "   - Drip Campaigns: drip-campaigns.php\n";
echo "   - Message Templates: message-templates.php\n";
echo "   - Webhooks: webhook-manager.php\n";

