-- WhatsApp Mailbox Database Schema
-- Run this in phpMyAdmin or MySQL to create your database

CREATE DATABASE IF NOT EXISTS whatsapp_mailbox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE whatsapp_mailbox;

-- Contacts table - stores all WhatsApp contacts
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(255),
    profile_picture_url TEXT,
    last_message_time DATETIME,
    unread_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number),
    INDEX idx_last_message (last_message_time DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table - stores all incoming and outgoing messages
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(255) UNIQUE,
    contact_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message_type ENUM('text', 'image', 'audio', 'video', 'document', 'location', 'template') DEFAULT 'text',
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_body TEXT,
    media_url TEXT,
    media_mime_type VARCHAR(100),
    media_caption TEXT,
    status ENUM('sent', 'delivered', 'read', 'failed') DEFAULT 'sent',
    is_read BOOLEAN DEFAULT FALSE,
    timestamp DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    INDEX idx_contact (contact_id),
    INDEX idx_timestamp (timestamp DESC),
    INDEX idx_unread (is_read, timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration table - stores WhatsApp API settings
CREATE TABLE IF NOT EXISTS config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration (update these values)
INSERT INTO config (config_key, config_value) VALUES
('whatsapp_access_token', 'YOUR_ACCESS_TOKEN_HERE'),
('whatsapp_phone_number_id', 'YOUR_PHONE_NUMBER_ID_HERE'),
('webhook_verify_token', 'YOUR_VERIFY_TOKEN_HERE'),
('business_name', 'Your Business Name')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value);

-- Admin users table - for mailbox access
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (username: admin, password: admin123 - CHANGE THIS!)
INSERT INTO admin_users (username, password_hash, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');
