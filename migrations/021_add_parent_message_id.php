<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        // Add parent_message_id column to messages table
        if (!Capsule::schema()->hasColumn('messages', 'parent_message_id')) {
            Capsule::schema()->table('messages', function ($table) {
                $table->string('parent_message_id', 255)->nullable()->after('message_id');
                $table->index('parent_message_id');
            });
            
            echo "✅ Added parent_message_id column to messages table\n";
        } else {
            echo "ℹ️  parent_message_id column already exists\n";
        }
    },
    
    'down' => function() {
        // Remove parent_message_id column
        if (Capsule::schema()->hasColumn('messages', 'parent_message_id')) {
            Capsule::schema()->table('messages', function ($table) {
                $table->dropIndex(['parent_message_id']);
                $table->dropColumn('parent_message_id');
            });
            
            echo "✅ Removed parent_message_id column from messages table\n";
        }
    }
];
