<?php
/**
 * Migration: Convert to Multi-Tenant SaaS Application
 * 
 * This adds user_id to ALL tables for complete data isolation between tenants.
 * Each user gets their own:
 * - Contacts, Messages, Quick Replies, Broadcasts, Scheduled Messages
 * - Segments, Tags, Deals, Workflows, Auto-tag Rules, Webhooks
 * - API Credentials, Notes, Activities, Tasks, Templates
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

echo "🚀 Starting Multi-Tenant Conversion...\n\n";

// ============================================
// STEP 1: Create user_api_credentials table
// ============================================
if (!$schema->hasTable('user_api_credentials')) {
    $schema->create('user_api_credentials', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->unique();
        $table->string('api_access_token', 500);
        $table->string('api_phone_number_id', 100);
        $table->string('api_version', 20)->default('v18.0');
        $table->string('webhook_verify_token', 255);
        $table->string('business_name', 255)->nullable();
        $table->string('business_phone_number', 50)->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_webhook_at')->nullable();
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index(['user_id', 'is_active']);
    });
    echo "✅ Created user_api_credentials table\n";
} else {
    echo "⚠️  user_api_credentials already exists\n";
}

// ============================================
// STEP 2: Add user_id to ALL data tables
// ============================================

$tables_to_convert = [
    'contacts' => 'Contact information',
    'messages' => 'WhatsApp messages',
    'quick_replies' => 'Auto-reply templates',
    'broadcasts' => 'Broadcast campaigns',
    'broadcast_recipients' => 'Broadcast recipients',
    'scheduled_messages' => 'Scheduled messages',
    'segments' => 'Contact segments',
    'tags' => 'Tags',
    'contact_tag' => 'Contact-Tag relationships',
    'deals' => 'CRM deals',
    'workflows' => 'Automation workflows',
    'workflow_executions' => 'Workflow execution logs',
    'auto_tag_rules' => 'Auto-tagging rules',
    'webhooks' => 'External webhooks',
    'notes' => 'Contact notes',
    'internal_notes' => 'Internal notes',
    'activities' => 'Activity logs',
    'tasks' => 'Tasks',
    'message_templates' => 'Message templates',
    'drip_campaigns' => 'Drip campaigns',
    'drip_subscribers' => 'Drip campaign subscribers',
    'ip_commands' => 'IP commands'
];

foreach ($tables_to_convert as $table => $description) {
    if ($schema->hasTable($table)) {
        if (!$schema->hasColumn($table, 'user_id')) {
            $schema->table($table, function ($tableObj) {
                $tableObj->unsignedBigInteger('user_id')->nullable()->after('id');
                $tableObj->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $tableObj->index('user_id');
            });
            echo "✅ Added user_id to {$table} ({$description})\n";
        } else {
            echo "⚠️  {$table} already has user_id\n";
        }
    } else {
        echo "⚠️  Table {$table} doesn't exist yet\n";
    }
}

// ============================================
// STEP 3: Create user_settings table
// ============================================
if (!$schema->hasTable('user_settings')) {
    $schema->create('user_settings', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('timezone', 50)->default('UTC');
        $table->string('date_format', 20)->default('Y-m-d');
        $table->string('time_format', 20)->default('H:i:s');
        $table->string('language', 10)->default('en');
        $table->boolean('email_notifications')->default(true);
        $table->boolean('browser_notifications')->default(true);
        $table->json('notification_preferences')->nullable();
        $table->integer('messages_per_page')->default(50);
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->unique('user_id');
    });
    echo "✅ Created user_settings table\n";
} else {
    echo "⚠️  user_settings already exists\n";
}

// ============================================
// STEP 4: Create user_subscriptions table (for billing)
// ============================================
if (!$schema->hasTable('user_subscriptions')) {
    $schema->create('user_subscriptions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->enum('plan', ['free', 'starter', 'pro', 'enterprise'])->default('free');
        $table->enum('status', ['active', 'cancelled', 'expired', 'suspended'])->default('active');
        $table->integer('message_limit')->default(100); // Messages per month
        $table->integer('messages_used')->default(0);
        $table->integer('contact_limit')->default(50);
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamp('current_period_start')->nullable();
        $table->timestamp('current_period_end')->nullable();
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index(['user_id', 'status']);
    });
    echo "✅ Created user_subscriptions table\n";
} else {
    echo "⚠️  user_subscriptions already exists\n";
}

// ============================================
// STEP 5: Create user_usage_logs table
// ============================================
if (!$schema->hasTable('user_usage_logs')) {
    $schema->create('user_usage_logs', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->date('date');
        $table->integer('messages_sent')->default(0);
        $table->integer('messages_received')->default(0);
        $table->integer('broadcasts_sent')->default(0);
        $table->integer('api_calls')->default(0);
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->unique(['user_id', 'date']);
        $table->index('date');
    });
    echo "✅ Created user_usage_logs table\n";
} else {
    echo "⚠️  user_usage_logs already exists\n";
}

// ============================================
// STEP 6: Assign existing data to first admin user
// ============================================
echo "\n📊 Migrating existing data...\n";

$firstUser = Capsule::table('users')->where('role', 'admin')->first();
if ($firstUser) {
    $userId = $firstUser->id;
    echo "Found admin user: {$firstUser->username} (ID: {$userId})\n";
    
    // Update all tables with NULL user_id to the first admin
    foreach (array_keys($tables_to_convert) as $table) {
        if ($schema->hasTable($table) && $schema->hasColumn($table, 'user_id')) {
            $updated = Capsule::table($table)->whereNull('user_id')->update(['user_id' => $userId]);
            if ($updated > 0) {
                echo "  ✅ Assigned {$updated} {$table} records to user {$userId}\n";
            }
        }
    }
    
    // Create API credentials from current .env settings
    if (!Capsule::table('user_api_credentials')->where('user_id', $userId)->exists()) {
        Capsule::table('user_api_credentials')->insert([
            'user_id' => $userId,
            'api_access_token' => env('API_ACCESS_TOKEN', env('WHATSAPP_ACCESS_TOKEN', '')),
            'api_phone_number_id' => env('API_PHONE_NUMBER_ID', env('WHATSAPP_PHONE_NUMBER_ID', '')),
            'api_version' => env('API_VERSION', env('WHATSAPP_API_VERSION', 'v18.0')),
            'webhook_verify_token' => env('WEBHOOK_VERIFY_TOKEN', ''),
            'business_name' => env('APP_NAME', 'MessageHub'),
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "  ✅ Created API credentials for user {$userId}\n";
    }
    
    // Create default subscription
    if (!Capsule::table('user_subscriptions')->where('user_id', $userId)->exists()) {
        Capsule::table('user_subscriptions')->insert([
            'user_id' => $userId,
            'plan' => 'enterprise',
            'status' => 'active',
            'message_limit' => 999999,
            'messages_used' => 0,
            'contact_limit' => 999999,
            'current_period_start' => date('Y-m-d H:i:s'),
            'current_period_end' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "  ✅ Created subscription for user {$userId}\n";
    }
    
    // Create default settings
    if (!Capsule::table('user_settings')->where('user_id', $userId)->exists()) {
        Capsule::table('user_settings')->insert([
            'user_id' => $userId,
            'timezone' => 'Asia/Karachi',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s',
            'language' => 'en',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "  ✅ Created settings for user {$userId}\n";
    }
} else {
    echo "⚠️  No admin user found. Please create one first.\n";
}

echo "\n✅ Multi-Tenant Conversion Complete!\n\n";
echo "📝 Next Steps:\n";
echo "1. Update WhatsAppService to use user-specific API credentials\n";
echo "2. Add middleware to filter data by user_id\n";
echo "3. Update webhook.php to identify user from phone_number_id\n";
echo "4. Add registration/signup page for new tenants\n";
echo "5. Create admin panel to manage all users\n";

return [
    'up' => function() {
        echo "Multi-tenant migration applied successfully\n";
    },
    'down' => function() use ($schema, $tables_to_convert) {
        echo "Rolling back multi-tenant changes...\n";
        
        // Remove user_id columns from all tables
        foreach (array_keys($tables_to_convert) as $table) {
            if ($schema->hasTable($table) && $schema->hasColumn($table, 'user_id')) {
                $schema->table($table, function ($tableObj) {
                    $tableObj->dropForeign(['user_id']);
                    $tableObj->dropColumn('user_id');
                });
            }
        }
        
        // Drop new tables
        $schema->dropIfExists('user_usage_logs');
        $schema->dropIfExists('user_subscriptions');
        $schema->dropIfExists('user_settings');
        $schema->dropIfExists('user_api_credentials');
        
        echo "Rollback complete\n";
    }
];
