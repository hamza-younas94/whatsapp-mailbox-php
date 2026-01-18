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

class Migration_016_Enhance_Message_Types
{
    public function up()
    {
        // Check if message_type is ENUM and change to VARCHAR
        $schema = Capsule::connection()->getDoctrineSchemaManager();
        $table = $schema->listTableDetails('messages');
        
        if ($table->hasColumn('message_type')) {
            $column = $table->getColumn('message_type');
            
            // If it's an ENUM, change to VARCHAR
            if ($column->getType()->getName() === 'string' && method_exists($column, 'getPlatformOptions')) {
                // For MySQL, check if it's ENUM by looking at the actual SQL
                try {
                    Capsule::statement("ALTER TABLE `messages` MODIFY COLUMN `message_type` VARCHAR(50) DEFAULT 'text'");
                    echo "✅ Changed message_type from ENUM to VARCHAR(50)\n";
                } catch (\Exception $e) {
                    // Column might already be VARCHAR or different error
                    echo "⚠️  message_type column update: " . $e->getMessage() . "\n";
                    
                    // Try alternative approach - check actual column type
                    $columnInfo = Capsule::select("SHOW COLUMNS FROM `messages` WHERE Field = 'message_type'");
                    if (!empty($columnInfo)) {
                        $type = $columnInfo[0]->Type ?? '';
                        if (stripos($type, 'enum') !== false) {
                            // It's an ENUM, change it
                            Capsule::statement("ALTER TABLE `messages` MODIFY COLUMN `message_type` VARCHAR(50) DEFAULT 'text'");
                            echo "✅ Changed message_type from ENUM to VARCHAR(50)\n";
                        } else {
                            echo "✅ message_type is already VARCHAR or compatible type\n";
                        }
                    }
                }
            } else {
                echo "✅ message_type column exists and is not ENUM (likely already VARCHAR)\n";
            }
        } else {
            echo "⚠️  message_type column does not exist\n";
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
    }
    
    public function down()
    {
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
}

