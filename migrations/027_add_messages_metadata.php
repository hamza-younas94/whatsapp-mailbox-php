<?php
/**
 * Migration: Add metadata column to messages table
 * For storing JSON data like emoji, parent_message_id, and other message metadata
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        echo "Adding metadata column to messages table...\n";
        
        try {
            // Check if metadata column exists
            $hasMetadata = Capsule::schema()->hasColumn('messages', 'metadata');
            
            if (!$hasMetadata) {
                Capsule::schema()->table('messages', function ($table) {
                    $table->text('metadata')->nullable()->after('parent_message_id');
                });
                echo "✅ Added metadata column to messages table\n";
            } else {
                echo "⚠️  metadata column already exists in messages table\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error adding metadata column: " . $e->getMessage() . "\n";
            throw $e;
        }
        
        echo "Migration completed successfully!\n";
    },
    
    'down' => function() {
        echo "Removing metadata column from messages table...\n";
        
        try {
            if (Capsule::schema()->hasColumn('messages', 'metadata')) {
                Capsule::schema()->table('messages', function ($table) {
                    $table->dropColumn('metadata');
                });
                echo "✅ Removed metadata column from messages table\n";
            }
        } catch (Exception $e) {
            echo "❌ Error removing metadata column: " . $e->getMessage() . "\n";
            throw $e;
        }
        
        echo "Rollback completed successfully!\n";
    }
];
