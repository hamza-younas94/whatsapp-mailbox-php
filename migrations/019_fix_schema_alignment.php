<?php
/**
 * Migration 019: Align schema for scheduled messages and drip subscribers
 * - Add scheduled_for column if missing (used in indexes)
 * - Add/align drip_campaign_id column and index using campaign_id
 * - Add indexes that previously failed due to missing columns
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function () {
        $schema = Capsule::schema();

        // scheduled_messages: ensure scheduled_for exists and index is present
        if (!$schema->hasColumn('scheduled_messages', 'scheduled_for')) {
            $schema->table('scheduled_messages', function ($table) {
                $table->dateTime('scheduled_for')->nullable()->after('scheduled_at');
            });
            // backfill from scheduled_at
            Capsule::table('scheduled_messages')
                ->whereNull('scheduled_for')
                ->update(['scheduled_for' => Capsule::raw('scheduled_at')]);
        }

        // Ensure index on user/status/scheduled_for
        try {
            $existing = Capsule::select("SHOW INDEX FROM scheduled_messages WHERE Key_name = 'idx_scheduled_messages_user_status'");
            if (empty($existing)) {
                Capsule::statement("ALTER TABLE `scheduled_messages` ADD INDEX `idx_scheduled_messages_user_status` (`user_id`, `status`, `scheduled_for`)");
            }
        } catch (\Exception $e) {
            echo "⚠️  Could not add scheduled_messages index: " . $e->getMessage() . "\n";
        }

        // drip_subscribers: ensure drip_campaign_id exists and mirrors campaign_id
        if (!$schema->hasColumn('drip_subscribers', 'drip_campaign_id')) {
            $schema->table('drip_subscribers', function ($table) {
                $table->unsignedBigInteger('drip_campaign_id')->nullable()->after('campaign_id');
            });
            // backfill from existing campaign_id
            Capsule::table('drip_subscribers')
                ->whereNull('drip_campaign_id')
                ->update(['drip_campaign_id' => Capsule::raw('campaign_id')]);
        }

        // Ensure index on user_id/drip_campaign_id/status (if columns exist)
        try {
            $colsExist = $schema->hasColumn('drip_subscribers', 'user_id') && $schema->hasColumn('drip_subscribers', 'drip_campaign_id') && $schema->hasColumn('drip_subscribers', 'status');
            if ($colsExist) {
                $existingIdx = Capsule::select("SHOW INDEX FROM drip_subscribers WHERE Key_name = 'idx_drip_subscribers_user_campaign'");
                if (empty($existingIdx)) {
                    Capsule::statement("ALTER TABLE `drip_subscribers` ADD INDEX `idx_drip_subscribers_user_campaign` (`user_id`, `drip_campaign_id`, `status`)");
                }
            }
        } catch (\Exception $e) {
            echo "⚠️  Could not add drip_subscribers index: " . $e->getMessage() . "\n";
        }
    },
    'down' => function () {
        $schema = Capsule::schema();

        if ($schema->hasColumn('scheduled_messages', 'scheduled_for')) {
            $schema->table('scheduled_messages', function ($table) {
                $table->dropColumn('scheduled_for');
            });
        }

        try {
            Capsule::statement("ALTER TABLE `scheduled_messages` DROP INDEX `idx_scheduled_messages_user_status`");
        } catch (\Exception $e) {
            // ignore
        }

        if ($schema->hasColumn('drip_subscribers', 'drip_campaign_id')) {
            $schema->table('drip_subscribers', function ($table) {
                $table->dropColumn('drip_campaign_id');
            });
        }

        try {
            Capsule::statement("ALTER TABLE `drip_subscribers` DROP INDEX `idx_drip_subscribers_user_campaign`");
        } catch (\Exception $e) {
            // ignore
        }
    }
];
