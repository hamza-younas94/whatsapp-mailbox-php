<?php
/**
 * Migration: Add message limit tracking
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Add message counter to config table
        if (!$schema->hasColumn('config', 'message_count')) {
            $schema->table('config', function ($table) {
                $table->integer('message_count')->default(0)->after('value');
            });
        }
        
        // Insert message limit config
        Capsule::table('config')->updateOrInsert(
            ['key' => 'message_limit'],
            ['value' => '500', 'message_count' => 0]
        );
        
        Capsule::table('config')->updateOrInsert(
            ['key' => 'messages_sent_count'],
            ['value' => '0', 'message_count' => 0]
        );
    },
    
    'down' => function() {
        $schema = Capsule::schema();
        
        if ($schema->hasColumn('config', 'message_count')) {
            $schema->table('config', function ($table) {
                $table->dropColumn('message_count');
            });
        }
        
        Capsule::table('config')->whereIn('key', ['message_limit', 'messages_sent_count'])->delete();
    }
];
