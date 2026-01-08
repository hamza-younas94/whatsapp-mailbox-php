# WhatsApp Mailbox - Enhanced PHP Setup Guide

## 🚀 What's New (Enhanced Version)

This enhanced version includes:
- ✅ **Laravel Eloquent ORM** - Professional database management
- ✅ **Database Migrations** - Version-controlled schema changes
- ✅ **Dependency Management** - Composer package management
- ✅ **Input Validation** - Robust data validation
- ✅ **Error Logging** - Monolog integration
- ✅ **Environment Variables** - Secure configuration with dotenv
- ✅ **Service Layer** - Clean architecture with WhatsAppService
- ✅ **Helper Functions** - Reusable utility functions

---

## 📋 Prerequisites

1. **Namecheap Shared Hosting with:**
   - PHP 7.4+ (PHP 8.0+ recommended)
   - MySQL 5.7+
   - SSH/Terminal access (for Composer)
   - SSL certificate (for webhook)

2. **WhatsApp Business API Credentials:**
   - Access Token
   - Phone Number ID
   - Webhook Verify Token

---

## 🛠️ Installation Steps

### Step 1: Upload Files

1. **Connect via FTP or File Manager**
2. **Upload all files** to `public_html/` or your domain root
3. **Ensure folder structure:**
   ```
   public_html/
   ├── app/
   │   ├── Models/
   │   ├── Services/
   │   └── helpers.php
   ├── database/
   │   └── migrations/
   ├── storage/
   │   └── logs/
   ├── vendor/ (will be created by Composer)
   ├── bootstrap.php
   ├── composer.json
   ├── .env.example
   ├── index.php
   ├── login.php
   ├── auth.php
   ├── api.php
   ├── webhook.php
   └── .htaccess
   ```

### Step 2: Install Composer Dependencies

**Option A: Via SSH (Recommended)**

```bash
# SSH into your hosting
ssh your_username@your_domain.com

# Navigate to your project
cd public_html

# Install Composer (if not installed)
curl -sS https://getcomposer.org/installer | php

# Install dependencies
php composer.phar install --no-dev --optimize-autoloader
```

**Option B: Via cPanel Terminal**

1. Open cPanel > Terminal
2. Run:
   ```bash
   cd public_html
   curl -sS https://getcomposer.org/installer | php
   php composer.phar install --no-dev --optimize-autoloader
   ```

**Option C: Local Installation + Upload**

If SSH is not available:
1. Install dependencies locally:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
2. Upload the entire `vendor/` folder via FTP

### Step 3: Configure Environment

1. **Copy `.env.example` to `.env`:**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env` file:**
   ```env
   APP_NAME="WhatsApp Mailbox"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=your_database_name
   DB_USERNAME=your_db_username
   DB_PASSWORD=your_db_password

   WHATSAPP_ACCESS_TOKEN=your_actual_access_token
   WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id
   WHATSAPP_API_VERSION=v18.0
   WEBHOOK_VERIFY_TOKEN=create_your_own_random_token

   SESSION_LIFETIME=120
   SESSION_SECURE=true

   LOG_CHANNEL=daily
   LOG_LEVEL=error
   ```

### Step 4: Create Database

1. **In cPanel > MySQL® Databases:**
   - Create database: `whatsapp_mailbox`
   - Create user with strong password
   - Grant ALL PRIVILEGES to user

2. **Don't import database.sql** - we'll use migrations instead!

### Step 5: Run Migrations

**Via Browser:**
Navigate to: `https://yourdomain.com/database/migrate.php`

**Via SSH:**
```bash
php database/migrate.php
```

You should see:
```
Running migrations...

Running: 001_create_contacts_table.php... ✓ Done
Running: 002_create_messages_table.php... ✓ Done
Running: 003_create_admin_users_table.php... ✓ Done
Running: 004_create_config_table.php... ✓ Done

Migrations completed!
```

### Step 6: Set Permissions

```bash
chmod 755 database/
chmod 755 storage/
chmod 755 storage/logs/
chmod 644 .env
chmod 644 .htaccess
```

### Step 7: Configure WhatsApp Webhook

1. **Go to Facebook Developers Console**
2. **Navigate to:** WhatsApp > Configuration
3. **Set Webhook URL:** `https://yourdomain.com/webhook.php`
4. **Set Verify Token:** (same as in .env)
5. **Subscribe to fields:**
   - ✅ messages
   - ✅ message_status
6. **Test webhook** - should verify successfully!

### Step 8: Login & Test

1. **Navigate to:** `https://yourdomain.com/login.php`
2. **Login with:**
   - Username: `admin`
   - Password: `admin123`
3. **⚠️ Change password immediately!**

---

## 🔧 Project Structure

### Models (app/Models/)
- **Contact.php** - Contact management with relationships
- **Message.php** - Message handling with scopes
- **AdminUser.php** - User authentication
- **Config.php** - Application configuration

### Services (app/Services/)
- **WhatsAppService.php** - WhatsApp API integration

### Core Files
- **bootstrap.php** - Application initialization
- **auth.php** - Authentication logic
- **api.php** - RESTful API endpoints
- **webhook.php** - WhatsApp webhook handler

---

## 📦 Composer Packages

```json
{
  "illuminate/database": "^10.0",     // Eloquent ORM
  "illuminate/events": "^10.0",       // Event system
  "illuminate/validation": "^10.0",   // Input validation
  "vlucas/phpdotenv": "^5.5",        // Environment variables
  "monolog/monolog": "^3.0",         // Logging
  "guzzlehttp/guzzle": "^7.5"        // HTTP client
}
```

---

## 🎯 Key Features

### 1. Eloquent ORM
```php
// Clean, expressive queries
$contacts = Contact::with('lastMessage')
    ->withCount('unreadMessages')
    ->orderBy('last_message_time', 'desc')
    ->get();

// Relationships
$message->contact->name;
$contact->messages()->unread()->get();
```

### 2. Migrations
```php
// Version-controlled schema
php database/migrate.php
```

### 3. Validation
```php
$validation = validate($data, [
    'message' => 'required|max:4096',
    'to' => 'required'
]);
```

### 4. Service Layer
```php
$whatsappService = new WhatsAppService();
$result = $whatsappService->sendTextMessage($to, $message);
```

### 5. Helper Functions
```php
env('DB_HOST');              // Get environment variable
response_json($data);        // Send JSON response
logger($message, 'error');   // Log message
sanitize($input);            // Sanitize input
```

---

## 🐛 Troubleshooting

### "Class not found" error
```bash
# Regenerate autoloader
php composer.phar dump-autoload
```

### "Database connection failed"
- Check `.env` credentials
- Ensure database exists
- Test connection in phpMyAdmin

### Migrations fail
- Check database credentials
- Ensure MySQL user has CREATE privileges
- Check `storage/logs/app.log` for errors

### Composer not working
- Ask hosting provider to enable `proc_open` and `exec`
- Or install locally and upload `vendor/` folder

---

## 🔒 Security Best Practices

1. **Change default password immediately**
2. **Use strong `.env` values**
3. **Set `APP_DEBUG=false` in production**
4. **Keep `SESSION_SECURE=true` with HTTPS**
5. **Set proper file permissions:**
   ```bash
   chmod 644 .env
   chmod 755 directories
   chmod 644 php files
   ```
6. **Regular backups** of database
7. **Monitor logs** in `storage/logs/`

---

## 📊 Database Schema

Managed via migrations in `database/migrations/`:
- **001_create_contacts_table.php**
- **002_create_messages_table.php**
- **003_create_admin_users_table.php**
- **004_create_config_table.php**

---

## 🚀 Performance Tips

1. **Enable OPcache** (ask hosting provider)
2. **Use PHP 8.0+** for better performance
3. **Optimize Composer autoloader:**
   ```bash
   php composer.phar dump-autoload --optimize
   ```
4. **Regular database maintenance:**
   ```sql
   OPTIMIZE TABLE contacts, messages;
   ```

---

## 📝 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api.php/contacts` | GET | Get all contacts |
| `/api.php/messages?contact_id=X` | GET | Get messages |
| `/api.php/send` | POST | Send message |
| `/api.php/mark-read` | POST | Mark as read |
| `/api.php/search?q=query` | GET | Search messages |

---

## 🔄 Updating

To update dependencies:
```bash
php composer.phar update
```

To add new migration:
1. Create file in `database/migrations/`
2. Follow naming: `005_description.php`
3. Run `php database/migrate.php`

---

## 📞 Support

- **WhatsApp API:** [Facebook Developers](https://developers.facebook.com/docs/whatsapp)
- **Eloquent ORM:** [Laravel Documentation](https://laravel.com/docs/eloquent)
- **Composer:** [getcomposer.org](https://getcomposer.org/)

---

## ⚡ Quick Commands

```bash
# Install dependencies
php composer.phar install --no-dev --optimize-autoloader

# Run migrations
php database/migrate.php

# Check logs
tail -f storage/logs/app.log

# Test database connection
php -r "require 'bootstrap.php'; echo 'Connected!';"
```

---

**Your enhanced WhatsApp Mailbox is ready! 🎉**

With Eloquent ORM, migrations, and robust architecture, you now have a professional-grade application.
