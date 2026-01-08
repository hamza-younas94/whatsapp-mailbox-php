# WhatsApp Mailbox - Enhanced Version

Professional WhatsApp Business API mailbox with **Laravel Eloquent ORM**, database migrations, and robust architecture.

## ✨ Enhanced Features

### 🔥 New in This Version:
- **Laravel Eloquent ORM** - Clean, expressive database queries
- **Database Migrations** - Version-controlled schema management
- **Composer Packages** - Professional dependency management
- **Service Layer** - Clean architecture with WhatsAppService
- **Input Validation** - Robust data validation
- **Error Logging** - Monolog integration
- **Environment Config** - Secure .env configuration
- **Helper Functions** - Reusable utilities

### 📦 Original Features:
- Real-time message receiving
- Send/reply to messages
- Contact management
- Unread message badges
- Search functionality
- Message history
- Responsive design
- Secure authentication

## 🚀 Quick Start

### 1. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 2. Configure Environment
```bash
cp .env.example .env
# Edit .env with your credentials
```

### 3. Run Migrations
```bash
php database/migrate.php
```

### 4. Access Application
```
https://yourdomain.com/login.php
Username: admin
Password: admin123
```

## 📖 Full Setup Guide

See [SETUP_GUIDE.md](SETUP_GUIDE.md) for detailed step-by-step instructions.

## 🔧 Configuration

Edit `config.php` with your credentials:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');

// WhatsApp API
define('WHATSAPP_ACCESS_TOKEN', 'your_access_token');
define('WHATSAPP_PHONE_NUMBER_ID', 'your_phone_number_id');
define('WEBHOOK_VERIFY_TOKEN', 'your_verify_token');
```

## 📁 File Structure

```
whatsapp-mailbox/
├── index.php              # Main mailbox interface
├── login.php             # Login page
├── logout.php            # Logout handler
├── config.php            # Configuration file
├── auth.php              # Authentication system
├── api.php               # API endpoints
├── webhook.php           # WhatsApp webhook handler
├── database.sql          # Database schema
├── .htaccess            # Apache configuration
├── SETUP_GUIDE.md       # Detailed setup instructions
├── README.md            # This file
└── assets/
    ├── css/
    │   └── style.css    # Styles
    └── js/
        └── app.js       # JavaScript logic
```

## 🔒 Security

- Change default admin password immediately
- Use HTTPS (SSL certificate)
- Keep access tokens secure
- Set proper file permissions
- Regularly backup database

## 🆘 Troubleshooting

**Messages not appearing?**
- Check webhook configuration
- Verify database connection
- Check error.log file

**Cannot send messages?**
- Verify API credentials
- Check Access Token validity
- Ensure cURL is enabled

**Webhook not verifying?**
- Confirm HTTPS is enabled
- Check verify token matches
- Test URL accessibility

## 📱 Usage

1. **Login** to your mailbox at `https://yourdomain.com`
2. **View contacts** in the left sidebar
3. **Click a contact** to view conversation
4. **Type and send** replies using the message input
5. **Messages sync automatically** every 5 seconds

## 🎯 API Endpoints

- `GET /api.php/contacts` - Get all contacts
- `GET /api.php/messages?contact_id=X` - Get messages
- `POST /api.php/send` - Send message
- `POST /api.php/mark-read` - Mark as read
- `GET /api.php/search?q=query` - Search messages

## 📝 Default Credentials

**Username:** admin  
**Password:** admin123

⚠️ **Change these immediately after first login!**

## 🔄 Updates & Maintenance

- Regularly backup your database
- Keep PHP and MySQL updated
- Monitor error.log for issues
- Check WhatsApp API for updates

## 📞 Support Resources

- [WhatsApp Business API Docs](https://developers.facebook.com/docs/whatsapp)
- [Facebook Developer Console](https://developers.facebook.com/)
- [Namecheap Support](https://www.namecheap.com/support/)

## 📄 License

This project is provided as-is for personal and commercial use.

## 🙏 Credits

Built for easy deployment on Namecheap shared hosting with WhatsApp Business API integration.

---

**Happy messaging! 💚**

For detailed setup instructions, see [SETUP_GUIDE.md](SETUP_GUIDE.md)
