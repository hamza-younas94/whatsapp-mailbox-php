<?php
/**
 * Migration: Add media_url and media_mime_type columns to messages
 * These were missing from 010_add_media_support.php
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

// Add media_url if not exists
if (!$schema->hasColumn('messages', 'media_url')) {
    $schema->table('messages', function ($table) {
        $table->longText('media_url')->nullable()->after('message_body');
    });
    echo "✅ Added media_url column to messages table\n";
} else {
    echo "⚠️  media_url column already exists\n";
}

// Add media_mime_type if not exists
if (!$schema->hasColumn('messages', 'media_mime_type')) {
    $schema->table('messages', function ($table) {
        $table->string('media_mime_type')->nullable()->after('media_url');
    });
    echo "✅ Added media_mime_type column to messages table\n";
} else {
    echo "⚠️  media_mime_type column already exists\n";
}

// Add media_caption if not exists
if (!$schema->hasColumn('messages', 'media_caption')) {
    $schema->table('messages', function ($table) {
        $table->text('media_caption')->nullable()->after('media_mime_type');
    });
    echo "✅ Added media_caption column to messages table\n";
} else {
    echo "⚠️  media_caption column already exists\n";
}

return [
    'up' => function() {
        echo "Media URL and metadata columns migration completed\n";
    },
    'down' => function() {
        $schema = Capsule::schema();
        $schema->table('messages', function ($table) {
            if ($schema->hasColumn('messages', 'media_url')) {
                $table->dropColumn('media_url');
            }
            if ($schema->hasColumn('messages', 'media_mime_type')) {
                $table->dropColumn('media_mime_type');
            }
            if ($schema->hasColumn('messages', 'media_caption')) {
                $table->dropColumn('media_caption');
            }
        });
        echo "Media URL and metadata columns removed\n";
    }
];
