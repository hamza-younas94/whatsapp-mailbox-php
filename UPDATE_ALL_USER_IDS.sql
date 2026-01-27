-- ============================================
-- Update all user_id to 2 for all tables
-- Run these queries manually in your database
-- ============================================

-- 1. Update contacts table
UPDATE `contacts` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 2. Update messages table
UPDATE `messages` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 3. Update quick_replies table
UPDATE `quick_replies` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 4. Update broadcasts table
UPDATE `broadcasts` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 5. Update broadcast_recipients table
UPDATE `broadcast_recipients` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 6. Update scheduled_messages table
UPDATE `scheduled_messages` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 7. Update segments table
UPDATE `segments` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 8. Update tags table
UPDATE `tags` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 9. Update contact_tag table (junction table)
UPDATE `contact_tag` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 10. Update deals table
UPDATE `deals` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 11. Update workflows table
UPDATE `workflows` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 12. Update workflow_executions table
UPDATE `workflow_executions` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 13. Update auto_tag_rules table
UPDATE `auto_tag_rules` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 14. Update webhooks table
UPDATE `webhooks` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 15. Update notes table
UPDATE `notes` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 16. Update internal_notes table
UPDATE `internal_notes` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 17. Update activities table
UPDATE `activities` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 18. Update tasks table
UPDATE `tasks` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 19. Update message_templates table
UPDATE `message_templates` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 20. Update drip_campaigns table
UPDATE `drip_campaigns` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 21. Update drip_subscribers table
UPDATE `drip_subscribers` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 22. Update ip_commands table
UPDATE `ip_commands` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 23. Update user_subscriptions table
UPDATE `user_subscriptions` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 24. Update user_usage_logs table (if exists)
UPDATE `user_usage_logs` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- 25. Update user_preferences table (if exists)
UPDATE `user_preferences` SET `user_id` = 2 WHERE `user_id` IS NULL OR `user_id` = 0;

-- ============================================
-- Verify the updates worked (check counts)
-- ============================================

SELECT 'contacts' as table_name, COUNT(*) as total_rows, SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) as user_id_2_count FROM contacts
UNION ALL
SELECT 'messages', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM messages
UNION ALL
SELECT 'quick_replies', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM quick_replies
UNION ALL
SELECT 'broadcasts', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM broadcasts
UNION ALL
SELECT 'tags', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM tags
UNION ALL
SELECT 'deals', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM deals
UNION ALL
SELECT 'workflows', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM workflows
UNION ALL
SELECT 'ip_commands', COUNT(*), SUM(CASE WHEN user_id = 2 THEN 1 ELSE 0 END) FROM ip_commands;
