# WhatsApp Mailbox - Setup Guide for Namecheap Shared Hosting

## 📋 Prerequisites

1. **WhatsApp Business API Access**
   - Facebook Business Manager account
   - WhatsApp Business API credentials
   - Access Token
   - Phone Number ID
   - Webhook Verify Token (you create this)

2. **Namecheap Shared Hosting**
   - cPanel access
   - PHP 7.4+ with cURL enabled
   - MySQL database
   - SSL certificate (recommended for HTTPS)

---

## 🚀 Step-by-Step Installation

### Step 1: Upload Files to Namecheap

1. **Login to cPanel**
   - Go to your Namecheap hosting cPanel

2. **Open File Manager**
   - Navigate to `public_html` (or your domain's root directory)

3. **Upload Files**
   - Upload all files from the `whatsapp-mailbox` folder
   - Make sure the structure looks like this:
     ```
     public_html/
     ├── index.php
     ├── login.php
     ├── logout.php
     ├── config.php
     ├── auth.php
     ├── api.php
     ├── webhook.php
     ├── database.sql
     ├── assets/
     │   ├── css/
     │   │   └── style.css
     │   └── js/
     │       └── app.js
     └── .htaccess
     ```

### Step 2: Create MySQL Database

1. **In cPanel, go to "MySQL® Databases"**

2. **Create a new database:**
   - Database name: `whatsapp_mailbox` (or your choice)
   - Click "Create Database"

3. **Create a database user:**
   - Username: Choose a username
   - Password: Create a strong password
   - Click "Create User"

4. **Add user to database:**
   - Select the database and user
   - Grant "ALL PRIVILEGES"
   - Click "Make Changes"

5. **Import database schema:**
   - Go to phpMyAdmin
   - Select your database
   - Click "Import" tab
   - Choose `database.sql` file
   - Click "Go"

### Step 3: Configure the Application

1. **Edit `config.php`:**
   - Open the file in File Manager or FTP
   - Update these settings:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_cpanel_username_whatsapp_mailbox'); // Usually includes cPanel username
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');

// WhatsApp API Configuration
define('WHATSAPP_ACCESS_TOKEN', 'YOUR_ACTUAL_ACCESS_TOKEN');
define('WHATSAPP_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID');
define('WEBHOOK_VERIFY_TOKEN', 'create_your_own_random_token_here');

// Base URL
define('BASE_URL', 'https://yourdomain.com');
```

2. **Get WhatsApp API Credentials:**
   - Login to [Facebook Developers](https://developers.facebook.com/)
   - Go to your WhatsApp Business App
   - Navigate to WhatsApp > Getting Started
   - Copy your **Access Token** (long-term token recommended)
   - Copy your **Phone Number ID**
   - Create a **Verify Token** (any random string you choose)

### Step 4: Create .htaccess File (URL Rewriting)

Create a `.htaccess` file in your root directory with this content:

```apache
# Enable URL Rewriting
RewriteEngine On

# Redirect to HTTPS (if you have SSL)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API Routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api\.php/(.*)$ api.php [L,QSA]

# Error Pages
ErrorDocument 404 /404.html
ErrorDocument 500 /500.html

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# PHP Settings
php_value upload_max_filesize 20M
php_value post_max_size 20M
php_value max_execution_time 300
php_value max_input_time 300
</apache>
```

### Step 5: Set Up WhatsApp Webhook

1. **Go to Facebook Developers Console**
   - Navigate to your WhatsApp Business App
   - Go to WhatsApp > Configuration

2. **Configure Webhook:**
   - **Callback URL:** `https://yourdomain.com/webhook.php`
   - **Verify Token:** (the same token you set in config.php)
   - Click "Verify and Save"

3. **Subscribe to Webhook Fields:**
   - Check these fields:
     - ✅ messages
     - ✅ message_status
   - Click "Subscribe"

4. **Test the Webhook:**
   - Facebook will send a GET request to verify
   - If successful, you'll see a green checkmark

### Step 6: Change Default Password

1. **Login to your mailbox:**
   - Go to `https://yourdomain.com/login.php`
   - Username: `admin`
   - Password: `admin123`

2. **Change the password immediately:**
   - Go to phpMyAdmin
   - Open `admin_users` table
   - Update the password using this PHP code:

```php
<?php
// Run this once to generate a new password hash
$new_password = 'YourNewSecurePassword123!';
$hash = password_hash($new_password, PASSWORD_DEFAULT);
echo $hash;
// Copy the output and update it in the database
?>
```

### Step 7: Test the System

1. **Send a test message:**
   - From your personal WhatsApp, send a message to your Business number
   - The message should appear in your mailbox within a few seconds

2. **Reply to a message:**
   - Click on a contact in the mailbox
   - Type a reply and send
   - The recipient should receive it on WhatsApp

3. **Check logs:**
   - If something doesn't work, check `error.log` in your root directory

---

## 🔧 Troubleshooting

### Messages Not Appearing in Mailbox

1. **Check webhook connection:**
   - Make sure the webhook is verified in Facebook Developers
   - Check `error.log` for any PHP errors

2. **Check database connection:**
   - Verify database credentials in `config.php`
   - Test database connection in phpMyAdmin

3. **Check file permissions:**
   - Make sure PHP can write to `error.log`
   - Set permissions: `chmod 644` for files, `chmod 755` for directories

### Cannot Send Messages

1. **Verify API credentials:**
   - Make sure Access Token is valid and not expired
   - Check Phone Number ID is correct

2. **Check API limits:**
   - WhatsApp has rate limits
   - New accounts have restricted sending capabilities

3. **Check cURL:**
   - Ensure cURL is enabled in PHP
   - Check with your hosting provider

### Webhook Not Verifying

1. **Check URL accessibility:**
   - Make sure `webhook.php` is accessible
   - Test: `https://yourdomain.com/webhook.php`

2. **Check verify token:**
   - Must match exactly in both config.php and Facebook

3. **Check SSL:**
   - Facebook requires HTTPS for webhooks
   - Install SSL certificate from Namecheap

---

## 🔒 Security Recommendations

1. **Change default credentials immediately**
2. **Use strong passwords**
3. **Enable HTTPS (SSL certificate)**
4. **Keep Access Tokens secure**
5. **Regularly update PHP and dependencies**
6. **Set proper file permissions:**
   ```bash
   chmod 644 *.php
   chmod 644 *.sql
   chmod 755 assets/
   ```
7. **Hide sensitive files:**
   - Add to `.htaccess`:
   ```apache
   <Files "config.php">
       Order Allow,Deny
       Deny from all
   </Files>
   ```

---

## 📱 Features

✅ **Real-time message receiving**
✅ **Send text messages**
✅ **Contact management**
✅ **Unread message badges**
✅ **Message history**
✅ **Search contacts**
✅ **Mark as read**
✅ **Responsive design**
✅ **Secure authentication**

---

## 🆘 Support & Resources

- **WhatsApp Business API Documentation:** https://developers.facebook.com/docs/whatsapp
- **Facebook Developer Console:** https://developers.facebook.com/
- **Namecheap Support:** https://www.namecheap.com/support/

---

## 📝 Important Notes

1. **WhatsApp Business API Approval:**
   - Your business must be verified by Facebook
   - Some features require approval

2. **Rate Limits:**
   - Free tier has limited messaging capabilities
   - Check Facebook's pricing for higher limits

3. **Message Templates:**
   - You need approved templates for outbound messages
   - This mailbox is designed primarily for responding to customer-initiated conversations

4. **Data Privacy:**
   - Comply with GDPR/privacy laws
   - Secure customer data properly

5. **Backup:**
   - Regularly backup your database
   - Keep backups of your Access Token and credentials

---

## 🎯 Next Steps

1. **Customize branding** - Update colors and logo
2. **Add media support** - Implement image/video sending
3. **Add templates** - Create message templates
4. **Add analytics** - Track message statistics
5. **Add auto-replies** - Implement automated responses

---

**Your WhatsApp Mailbox is now ready! 🎉**

If you encounter any issues, check the `error.log` file or contact your hosting provider for PHP/MySQL configuration support.
