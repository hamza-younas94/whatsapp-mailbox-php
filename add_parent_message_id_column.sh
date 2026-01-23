#!/bin/bash

# Quick fix to add parent_message_id column to messages table
# Run this on the server: bash add_parent_message_id_column.sh

echo "🔧 Adding parent_message_id column to messages table..."

# SQL to add the column
mysql -u pakmfguk_wp469 -p pakmfguk_wp469 << 'EOF'
ALTER TABLE messages 
ADD COLUMN parent_message_id VARCHAR(255) NULL AFTER message_id,
ADD INDEX idx_parent_message_id (parent_message_id);

SELECT 'Column added successfully!' as status;
EOF

echo "✅ Done! Column parent_message_id has been added to messages table."
