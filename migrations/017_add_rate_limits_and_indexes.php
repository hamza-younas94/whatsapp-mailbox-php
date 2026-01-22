<?php
/**
 * Migration 017: Rate limiting storage and performance indexes
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function () {
        // Rate limits table
        if (!Capsule::schema()->hasTable('rate_limits')) {
            Capsule::schema()->create('rate_limits', function ($table) {
                $table->increments('id');
                $table->integer('user_id')->nullable()->index();
                $table->string('action', 100);
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('window_start');
                $table->integer('count')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'action', 'window_start'], 'uniq_rate_limit_window');
            });
            echo "✅ Created rate_limits table\n";
        } else {
            echo "✅ rate_limits table already exists\n";
        }

        // Performance indexes on common lookup columns
        $indexes = [
            ['table' => 'contacts', 'name' => 'idx_contacts_user_created', 'sql' => "ALTER TABLE `contacts` ADD INDEX `idx_contacts_user_created` (`user_id`, `created_at`)", 'columns' => ['user_id', 'created_at']],
            ['table' => 'messages', 'name' => 'idx_messages_user_contact_created', 'sql' => "ALTER TABLE `messages` ADD INDEX `idx_messages_user_contact_created` (`user_id`, `contact_id`, `created_at`)", 'columns' => ['user_id', 'contact_id', 'created_at']],
            ['table' => 'notes', 'name' => 'idx_notes_user_contact', 'sql' => "ALTER TABLE `notes` ADD INDEX `idx_notes_user_contact` (`user_id`, `contact_id`, `created_at`)", 'columns' => ['user_id', 'contact_id', 'created_at']],
            ['table' => 'deals', 'name' => 'idx_deals_user_contact', 'sql' => "ALTER TABLE `deals` ADD INDEX `idx_deals_user_contact` (`user_id`, `contact_id`, `deal_date`)", 'columns' => ['user_id', 'contact_id', 'deal_date']],
            ['table' => 'activities', 'name' => 'idx_activities_user_contact', 'sql' => "ALTER TABLE `activities` ADD INDEX `idx_activities_user_contact` (`user_id`, `contact_id`, `created_at`)", 'columns' => ['user_id', 'contact_id', 'created_at']],
            ['table' => 'tasks', 'name' => 'idx_tasks_user_contact', 'sql' => "ALTER TABLE `tasks` ADD INDEX `idx_tasks_user_contact` (`user_id`, `contact_id`, `created_at`)", 'columns' => ['user_id', 'contact_id', 'created_at']],
            ['table' => 'broadcasts', 'name' => 'idx_broadcasts_user_status', 'sql' => "ALTER TABLE `broadcasts` ADD INDEX `idx_broadcasts_user_status` (`user_id`, `status`, `scheduled_at`)", 'columns' => ['user_id', 'status', 'scheduled_at']],
            ['table' => 'scheduled_messages', 'name' => 'idx_scheduled_messages_user_status', 'sql' => "ALTER TABLE `scheduled_messages` ADD INDEX `idx_scheduled_messages_user_status` (`user_id`, `status`, `scheduled_for`)", 'columns' => ['user_id', 'status', 'scheduled_for']],
            ['table' => 'workflow_executions', 'name' => 'idx_workflow_exec_user_time', 'sql' => "ALTER TABLE `workflow_executions` ADD INDEX `idx_workflow_exec_user_time` (`user_id`, `workflow_id`, `executed_at`)", 'columns' => ['user_id', 'workflow_id', 'executed_at']],
            ['table' => 'drip_subscribers', 'name' => 'idx_drip_subscribers_user_campaign', 'sql' => "ALTER TABLE `drip_subscribers` ADD INDEX `idx_drip_subscribers_user_campaign` (`user_id`, `drip_campaign_id`, `status`)", 'columns' => ['user_id', 'drip_campaign_id', 'status']],
            ['table' => 'webhook_deliveries', 'name' => 'idx_webhook_deliveries_user_status', 'sql' => "ALTER TABLE `webhook_deliveries` ADD INDEX `idx_webhook_deliveries_user_status` (`user_id`, `status`, `attempted_at`)", 'columns' => ['user_id', 'status', 'attempted_at']],
        ];

        foreach ($indexes as $index) {
            try {
                if (!Capsule::schema()->hasTable($index['table'])) {
                    continue;
                }

                $existing = Capsule::select("SHOW INDEXES FROM `{$index['table']}` WHERE Key_name = ?", [$index['name']]);
                if (empty($existing)) {
                    Capsule::statement($index['sql']);
                    echo "✅ Added index {$index['name']} on {$index['table']}\n";
                } else {
                    echo "✅ Index {$index['name']} already exists on {$index['table']}\n";
                }
            } catch (\Exception $e) {
                echo "⚠️  Index {$index['name']} on {$index['table']} failed: " . $e->getMessage() . "\n";
            }
        }
    },

    'down' => function () {
        // Drop rate_limits table
        if (Capsule::schema()->hasTable('rate_limits')) {
            Capsule::schema()->drop('rate_limits');
            echo "✅ Dropped rate_limits table\n";
        }

        // Drop indexes created above (best-effort)
        $indexes = [
            ['table' => 'contacts', 'name' => 'idx_contacts_user_created'],
            ['table' => 'messages', 'name' => 'idx_messages_user_contact_created'],
            ['table' => 'notes', 'name' => 'idx_notes_user_contact'],
            ['table' => 'deals', 'name' => 'idx_deals_user_contact'],
            ['table' => 'activities', 'name' => 'idx_activities_user_contact'],
            ['table' => 'tasks', 'name' => 'idx_tasks_user_contact'],
            ['table' => 'broadcasts', 'name' => 'idx_broadcasts_user_status'],
            ['table' => 'scheduled_messages', 'name' => 'idx_scheduled_messages_user_status'],
            ['table' => 'workflow_executions', 'name' => 'idx_workflow_exec_user_time'],
            ['table' => 'drip_subscribers', 'name' => 'idx_drip_subscribers_user_campaign'],
            ['table' => 'webhook_deliveries', 'name' => 'idx_webhook_deliveries_user_status'],
        ];

        foreach ($indexes as $index) {
            try {
                if (!Capsule::schema()->hasTable($index['table'])) {
                    continue;
                }
                Capsule::statement("ALTER TABLE `{$index['table']}` DROP INDEX `{$index['name']}`");
                echo "✅ Dropped index {$index['name']} on {$index['table']}\n";
            } catch (\Exception $e) {
                echo "⚠️  Drop index {$index['name']} failed: " . $e->getMessage() . "\n";
            }
        }
    },
];
