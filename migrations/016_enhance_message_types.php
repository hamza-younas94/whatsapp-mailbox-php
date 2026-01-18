<?php
/**
 * Migration 016: Enhance message types support
 * 
 * Changes message_type from ENUM to VARCHAR to support all WhatsApp message types:
 * - text, image, audio, video, document, location, template (existing)
 * - contacts, sticker, reaction, interactive, button, list (new)
 * - unsupported, system, notification (new)
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        // Check if message_type is ENUM and change to VARCHAR
        // First, check the actual column type using SQL
        try {
            $columnInfo = Capsule::select("SHOW COLUMNS FROM `messages` WHERE Field = 'message_type'");
            if (!empty($columnInfo)) {
                $type = $columnInfo[0]->Type ?? '';
                if (stripos($type, 'enum') !== false) {
                    // It's an ENUM, change it to VARCHAR
                    Capsule::statement("ALTER TABLE `messages` MODIFY COLUMN `message_type` VARCHAR(50) DEFAULT 'text'");
                    echo "✅ Changed message_type from ENUM to VARCHAR(50)\n";
                } else {
                    // Check if it's VARCHAR(50) or compatible
                    if (stripos($type, 'varchar(50)') !== false || stripos($type, 'varchar') !== false) {
                        echo "✅ message_type is already VARCHAR (type: {$type})\n";
                    } else {
                        // Try to change it anyway
                        Capsule::statement("ALTER TABLE `messages` MODIFY COLUMN `message_type` VARCHAR(50) DEFAULT 'text'");
                        echo "✅ Changed message_type to VARCHAR(50)\n";
                    }
                }
            } else {
                echo "⚠️  message_type column does not exist\n";
            }
        } catch (\Exception $e) {
            echo "⚠️  message_type column update: " . $e->getMessage() . "\n";
        }
        
        // Add index on message_type for better query performance
        try {
            $indexes = Capsule::select("SHOW INDEXES FROM `messages` WHERE Key_name = 'idx_message_type'");
            if (empty($indexes)) {
                Capsule::statement("ALTER TABLE `messages` ADD INDEX `idx_message_type` (`message_type`)");
                echo "✅ Added index on message_type\n";
            } else {
                echo "✅ Index idx_message_type already exists\n";
            }
        } catch (\Exception $e) {
            echo "⚠️  Index creation: " . $e->getMessage() . "\n";
        }
    },
    
    'down' => function() {
        // Revert to ENUM (optional - usually we don't want to lose data)
        try {
            Capsule::statement("ALTER TABLE `messages` MODIFY COLUMN `message_type` ENUM('text', 'image', 'audio', 'video', 'document', 'location', 'template') DEFAULT 'text'");
            echo "✅ Reverted message_type to ENUM\n";
        } catch (\Exception $e) {
            echo "⚠️  Revert failed: " . $e->getMessage() . "\n";
        }
        
        // Remove index
        try {
            Capsule::statement("ALTER TABLE `messages` DROP INDEX `idx_message_type`");
            echo "✅ Removed index idx_message_type\n";
        } catch (\Exception $e) {
            echo "⚠️  Index removal: " . $e->getMessage() . "\n";
        }
    }
];

