-- Add parent_message_id column to messages table for reaction linking
-- Run this SQL in phpMyAdmin or MySQL console

-- Check if column exists first
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'messages' 
  AND COLUMN_NAME = 'parent_message_id';

-- Add column if it doesn't exist
SET @query = IF(@col_exists = 0, 
    'ALTER TABLE messages ADD COLUMN parent_message_id VARCHAR(255) NULL AFTER message_id, ADD INDEX idx_parent_message_id (parent_message_id)',
    'SELECT "Column parent_message_id already exists" as status'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the column was added
SELECT 'SUCCESS: parent_message_id column ready!' as result;
