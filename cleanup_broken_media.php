<?php
/**
 * Clean up broken WhatsApp media URLs from database
 * Run once: php cleanup_broken_media.php
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

try {
    echo "🧹 Cleaning up broken media URLs...\n\n";
    
    // Clear media_url for incoming messages with expired WhatsApp links
    // Keep only messages with local media_filename
    $updated = Capsule::table('messages')
        ->where('direction', 'incoming')
        ->whereIn('message_type', ['image', 'video', 'audio', 'document'])
        ->whereNotNull('media_url')
        ->where(function($query) {
            $query->whereNull('media_filename')
                  ->orWhere('media_filename', '');
        })
        ->where('media_url', 'like', '%lookaside.fbsbx.com%')
        ->update(['media_url' => null]);
    
    echo "✅ Cleared {$updated} expired WhatsApp media URLs\n";
    
    // Get count of messages with local files (these are OK)
    $localCount = Capsule::table('messages')
        ->whereIn('message_type', ['image', 'video', 'audio', 'document'])
        ->whereNotNull('media_filename')
        ->where('media_filename', '!=', '')
        ->count();
    
    echo "✅ {$localCount} messages have locally downloaded media\n\n";
    
    echo "🎯 Done! Refresh your browser to see the changes.\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
