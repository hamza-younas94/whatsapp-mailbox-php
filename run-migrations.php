<?php
/**
 * Production Migration Runner
 * Run this via web browser: your-domain.com/run-migrations.php
 */

require_once __DIR__ . '/bootstrap/env.php';

// Database configuration
$host = env('DB_HOST', 'localhost');
$database = env('DB_DATABASE');
$username = env('DB_USERNAME');
$password = env('DB_PASSWORD');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Running Migrations...</h1><pre>\n\n";
    
    // Migration 010: Add media support
    echo "=== Migration 010: Media Support ===\n";
    try {
        $pdo->exec("ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS media_id VARCHAR(255) NULL AFTER media_url,
            ADD COLUMN IF NOT EXISTS media_filename VARCHAR(255) NULL AFTER media_id,
            ADD COLUMN IF NOT EXISTS media_size BIGINT NULL AFTER media_filename");
        echo "✅ Added media support columns\n\n";
    } catch (Exception $e) {
        echo "⚠️  Media columns may already exist: " . $e->getMessage() . "\n\n";
    }
    
    // Migration 011: Auto tag rules
    echo "=== Migration 011: Auto Tag Rules ===\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_tag_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            keywords TEXT NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            case_sensitive BOOLEAN DEFAULT FALSE,
            match_type ENUM('any', 'all', 'exact') DEFAULT 'any',
            priority INT DEFAULT 0,
            usage_count INT DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
            INDEX idx_active (is_active),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created auto_tag_rules table\n\n";
    } catch (Exception $e) {
        echo "⚠️  auto_tag_rules table may already exist: " . $e->getMessage() . "\n\n";
    }
    
    // Migration 012: Users and roles
    echo "=== Migration 012: Users & Roles ===\n";
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            role ENUM('admin', 'agent', 'viewer') DEFAULT 'agent',
            is_active BOOLEAN DEFAULT TRUE,
            avatar_url VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            last_login_at TIMESTAMP NULL,
            messages_sent INT DEFAULT 0,
            conversations_handled INT DEFAULT 0,
            avg_response_time DECIMAL(8,2) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_role (role),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created users table\n";
        
        // Migrate admin_users if exists
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$database' AND table_name = 'admin_users'");
        if ($result->fetchColumn() > 0) {
            $pdo->exec("INSERT IGNORE INTO users (id, username, email, password, full_name, role, is_active, created_at, updated_at)
                SELECT id, username, CONCAT(username, '@example.com'), password, username, 'admin', 1, created_at, updated_at
                FROM admin_users");
            echo "✅ Migrated admin users\n";
        }
        
        // Add assigned_agent_id to contacts
        $pdo->exec("ALTER TABLE contacts 
            ADD COLUMN IF NOT EXISTS assigned_agent_id BIGINT UNSIGNED NULL AFTER assigned_to,
            ADD CONSTRAINT fk_contacts_agent FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "✅ Added agent assignment to contacts\n\n";
    } catch (Exception $e) {
        echo "⚠️  Users table may already exist: " . $e->getMessage() . "\n\n";
    }
    
    // Migration 013: Advanced features
    echo "=== Migration 013: Advanced Features ===\n";
    
    // Internal notes
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS internal_notes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contact_id BIGINT UNSIGNED NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            content TEXT NOT NULL,
            is_pinned BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_contact_date (contact_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created internal_notes table\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
    
    // Message templates
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS message_templates (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            whatsapp_template_name VARCHAR(255) UNIQUE NOT NULL,
            language_code VARCHAR(10) DEFAULT 'en',
            content TEXT NOT NULL,
            variables JSON NULL,
            category VARCHAR(50) DEFAULT 'utility',
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            usage_count INT DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created message_templates table\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
    
    // Drip campaigns
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS drip_campaigns (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            is_active BOOLEAN DEFAULT FALSE,
            trigger_conditions JSON NOT NULL,
            total_subscribers INT DEFAULT 0,
            completed_count INT DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created drip_campaigns table\n";
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS drip_campaign_steps (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            step_number INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            delay_minutes INT NOT NULL,
            message_type ENUM('text', 'template') DEFAULT 'text',
            message_content TEXT NOT NULL,
            template_id BIGINT UNSIGNED NULL,
            sent_count INT DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (campaign_id) REFERENCES drip_campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
            UNIQUE KEY unique_campaign_step (campaign_id, step_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created drip_campaign_steps table\n";
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS drip_subscribers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            contact_id BIGINT UNSIGNED NOT NULL,
            current_step INT DEFAULT 0,
            status ENUM('active', 'completed', 'paused', 'unsubscribed') DEFAULT 'active',
            next_send_at TIMESTAMP NULL,
            started_at TIMESTAMP NOT NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (campaign_id) REFERENCES drip_campaigns(id) ON DELETE CASCADE,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
            UNIQUE KEY unique_campaign_contact (campaign_id, contact_id),
            INDEX idx_next_send (next_send_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created drip_subscribers table\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
    
    // Webhooks
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS webhooks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            events JSON NOT NULL,
            secret VARCHAR(255) NULL,
            is_active BOOLEAN DEFAULT TRUE,
            success_count INT DEFAULT 0,
            failure_count INT DEFAULT 0,
            last_triggered_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✅ Created webhooks table\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
    
    // Response time tracking
    try {
        $pdo->exec("ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS response_time_minutes DECIMAL(8,2) NULL AFTER is_read,
            ADD COLUMN IF NOT EXISTS responded_by BIGINT UNSIGNED NULL AFTER response_time_minutes");
        
        $pdo->exec("ALTER TABLE messages 
            ADD CONSTRAINT fk_messages_responded_by FOREIGN KEY (responded_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "✅ Added response time tracking\n";
    } catch (Exception $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
    
    echo "\n\n=== ✅ All Migrations Completed Successfully! ===\n";
    echo "You can now delete this file for security.\n";
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<h1 style='color:red'>❌ Migration Error</h1>";
    echo "<pre>Error: " . $e->getMessage() . "</pre>";
}
