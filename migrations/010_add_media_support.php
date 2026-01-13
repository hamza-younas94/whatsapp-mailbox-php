<?php
/**
 * Migration: Add media support to messages
 */

use Illuminate\Database\Capsule\Manager as Capsule;

// Add media columns to messages table if not exists
$schema = Capsule::schema();

if (!$schema->hasColumn('messages', 'media_id')) {
    $schema->table('messages', function ($table) {
        $table->string('media_id')->nullable()->after('media_url');
        $table->string('media_filename')->nullable()->after('media_id');
        $table->bigInteger('media_size')->nullable()->after('media_filename');
    });
    
    echo "✅ Added media support columns to messages table\n";
} else {
    echo "⚠️  Media columns already exist\n";
}

return [
    'up' => function() {
        echo "Media support migration completed\n";
    },
    'down' => function() {
        $schema = Capsule::schema();
        $schema->table('messages', function ($table) {
            $table->dropColumn(['media_id', 'media_filename', 'media_size']);
        });
        echo "Media support columns removed\n";
    }
];
