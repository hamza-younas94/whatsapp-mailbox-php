<?php
/**
 * Migration: Enhance quick replies with advanced features
 * Adds: priority, business hours, conditions, multiple shortcuts, regex, delays, media, etc.
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Check if quick_replies table exists
        if (!$schema->hasTable('quick_replies')) {
            echo "⚠️  quick_replies table does not exist. Please run migration 005 first.\n";
            return;
        }
        
        if (!$schema->hasTable('quick_replies')) {
            echo "⚠️  quick_replies table does not exist. Please run previous migrations first.\n";
            return;
        }
        
        // Priority field (for ordering matches)
        if (!$schema->hasColumn('quick_replies', 'priority')) {
            $schema->table('quick_replies', function ($table) {
                $table->integer('priority')->default(0)->after('is_active');
            });
            echo "✅ Added priority field\n";
        }
        
        // Business hours support
        if (!$schema->hasColumn('quick_replies', 'business_hours_start')) {
            $schema->table('quick_replies', function ($table) {
                $table->time('business_hours_start')->nullable()->after('priority');
                $table->time('business_hours_end')->nullable()->after('business_hours_start');
                $table->string('timezone', 50)->default('UTC')->after('business_hours_end');
                $table->text('outside_hours_message')->nullable()->after('timezone');
            });
            echo "✅ Added business hours fields\n";
        }
        
        // Multiple shortcuts (stored as JSON array)
        if (!$schema->hasColumn('quick_replies', 'shortcuts')) {
            $schema->table('quick_replies', function ($table) {
                $table->json('shortcuts')->nullable()->after('shortcut');
            });
            
            // Migrate existing shortcut to shortcuts array
            $replies = Capsule::table('quick_replies')->get();
            foreach ($replies as $reply) {
                if ($reply->shortcut) {
                    Capsule::table('quick_replies')
                        ->where('id', $reply->id)
                        ->update(['shortcuts' => json_encode([$reply->shortcut])]);
                }
            }
            echo "✅ Added shortcuts field and migrated data\n";
        }
        
        // Conditions for conditional replies
        if (!$schema->hasColumn('quick_replies', 'conditions')) {
            $schema->table('quick_replies', function ($table) {
                $table->json('conditions')->nullable()->after('shortcuts');
            });
            echo "✅ Added conditions field\n";
        }
        
        // Regex pattern matching
        if (!$schema->hasColumn('quick_replies', 'use_regex')) {
            $schema->table('quick_replies', function ($table) {
                $table->boolean('use_regex')->default(false)->after('conditions');
            });
            echo "✅ Added use_regex field\n";
        }
        
        // Reply delay
        if (!$schema->hasColumn('quick_replies', 'delay_seconds')) {
            $schema->table('quick_replies', function ($table) {
                $table->integer('delay_seconds')->default(0)->after('use_regex');
            });
            echo "✅ Added delay_seconds field\n";
        }
        
        // Media support
        if (!$schema->hasColumn('quick_replies', 'media_url')) {
            $schema->table('quick_replies', function ($table) {
                $table->string('media_url', 500)->nullable()->after('delay_seconds');
                $table->string('media_type', 50)->nullable()->after('media_url');
                $table->string('media_filename')->nullable()->after('media_type');
            });
            echo "✅ Added media support fields\n";
        }
        
        // Contact filtering
        if (!$schema->hasColumn('quick_replies', 'excluded_contact_ids')) {
            $schema->table('quick_replies', function ($table) {
                $table->json('excluded_contact_ids')->nullable()->after('media_filename');
                $table->json('included_contact_ids')->nullable()->after('excluded_contact_ids');
            });
            echo "✅ Added contact filtering fields\n";
        }
        
        // Reply sequences (multi-message)
        if (!$schema->hasColumn('quick_replies', 'sequence_messages')) {
            $schema->table('quick_replies', function ($table) {
                $table->json('sequence_messages')->nullable()->after('included_contact_ids');
                $table->integer('sequence_delay_seconds')->default(2)->after('sequence_messages');
            });
            echo "✅ Added reply sequence fields\n";
        }
        
        // Analytics
        if (!$schema->hasColumn('quick_replies', 'success_count')) {
            $schema->table('quick_replies', function ($table) {
                $table->integer('success_count')->default(0)->after('usage_count');
                $table->integer('failure_count')->default(0)->after('success_count');
                $table->timestamp('last_used_at')->nullable()->after('failure_count');
            });
            echo "✅ Added analytics fields\n";
        }
        
        // Group message support
        if (!$schema->hasColumn('quick_replies', 'allow_groups')) {
            $schema->table('quick_replies', function ($table) {
                $table->boolean('allow_groups')->default(false)->after('last_used_at');
            });
            echo "✅ Added allow_groups field\n";
        }
        
        echo "✅ Quick replies enhancement migration completed!\n";
    },
    
    'down' => function() {
        $schema = Capsule::schema();
        
        if ($schema->hasTable('quick_replies')) {
            $schema->table('quick_replies', function ($table) {
                $columns = [
                    'priority', 'business_hours_start', 'business_hours_end', 'timezone',
                    'outside_hours_message', 'shortcuts', 'conditions', 'use_regex',
                    'delay_seconds', 'media_url', 'media_type', 'media_filename',
                    'excluded_contact_ids', 'included_contact_ids', 'sequence_messages',
                    'sequence_delay_seconds', 'success_count', 'failure_count',
                    'last_used_at', 'allow_groups'
                ];
                
                foreach ($columns as $column) {
                    if ($schema->hasColumn('quick_replies', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
            echo "✅ Reverted quick replies enhancements\n";
        }
    }
];

