<?php
/**
 * Migration: Add message limit tracking
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Config table already exists with config_key and config_value columns
        // Just insert the new config records
        
        Capsule::table('config')->updateOrInsert(
            ['config_key' => 'message_limit'],
            ['config_value' => '500', 'created_at' => now(), 'updated_at' => now()]
        );
        
        Capsule::table('config')->updateOrInsert(
            ['config_key' => 'messages_sent_count'],
            ['config_value' => '0', 'created_at' => now(), 'updated_at' => now()]
        );
    },
    
    'down' => function() {
        Capsule::table('config')->whereIn('config_key', ['message_limit', 'messages_sent_count'])->delete();
    }
];
