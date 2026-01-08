# WhatsApp Mailbox - Quick Reference

## 🔑 Quick Setup Checklist

- [ ] Upload files to Namecheap cPanel
- [ ] Create MySQL database
- [ ] Import database.sql in phpMyAdmin
- [ ] Update config.php with database credentials
- [ ] Update config.php with WhatsApp API credentials
- [ ] Upload .htaccess file
- [ ] Set webhook URL in Facebook Developers
- [ ] Test webhook verification
- [ ] Login and change default password
- [ ] Send test message

## 📝 WhatsApp API Credentials Needed

1. **Access Token** - From Facebook Developers Console
2. **Phone Number ID** - From WhatsApp Business API setup
3. **Webhook Verify Token** - Create your own random string

## 🌐 Webhook URL Format

```
https://yourdomain.com/webhook.php
```

## 🗄️ Database Tables

- `contacts` - Stores WhatsApp contacts
- `messages` - Stores all messages (incoming/outgoing)
- `admin_users` - Admin authentication
- `config` - Application settings

## 🎨 Customization Tips

### Change Colors
Edit [assets/css/style.css](assets/css/style.css):
```css
:root {
    --primary-color: #25D366;  /* WhatsApp green */
    --secondary-color: #128C7E;
}
```

### Add Business Logo
Replace WhatsApp icon in [login.php](login.php) and [index.php](index.php)

### Modify Business Name
Update in database or [config.php](config.php)

## 🔍 File Purposes

| File | Purpose |
|------|---------|
| `index.php` | Main mailbox interface |
| `login.php` | Admin login page |
| `webhook.php` | Receives WhatsApp messages |
| `api.php` | Handles sending messages |
| `config.php` | Configuration settings |
| `auth.php` | Authentication logic |
| `database.sql` | Database structure |
| `.htaccess` | Apache/server configuration |

## ⚡ Performance Tips

1. **Enable caching** in .htaccess (already included)
2. **Use compression** for faster loading
3. **Optimize images** if you add media support
4. **Regular database cleanup** for old messages

## 🐛 Common Errors & Solutions

### "Database connection failed"
- Check database credentials in config.php
- Verify database exists in phpMyAdmin

### "Webhook verification failed"
- Ensure verify token matches in config.php and Facebook
- Check that webhook.php is accessible via HTTPS

### "Failed to send message"
- Verify Access Token is valid
- Check Phone Number ID is correct
- Ensure you're not rate-limited

### "Unauthorized" when accessing API
- Make sure you're logged in
- Check session configuration in config.php

## 📊 Database Queries (Useful)

### Change admin password:
```sql
UPDATE admin_users 
SET password_hash = '$2y$10$YOUR_NEW_HASH_HERE' 
WHERE username = 'admin';
```

### View unread message count:
```sql
SELECT COUNT(*) 
FROM messages 
WHERE is_read = FALSE AND direction = 'incoming';
```

### Get most active contacts:
```sql
SELECT c.name, COUNT(m.id) as message_count 
FROM contacts c 
JOIN messages m ON c.id = m.contact_id 
GROUP BY c.id 
ORDER BY message_count DESC 
LIMIT 10;
```

## 🔐 Security Best Practices

1. ✅ Change default password immediately
2. ✅ Use strong passwords (12+ characters)
3. ✅ Enable HTTPS/SSL certificate
4. ✅ Keep Access Token secure
5. ✅ Regular backups
6. ✅ Update PHP regularly
7. ✅ Limit file permissions
8. ✅ Monitor error.log

## 📱 Mobile Responsive

The interface is fully responsive and works on:
- 📱 Mobile phones (iOS/Android)
- 📱 Tablets
- 💻 Desktop browsers

## 🎯 Feature Roadmap (Future Enhancements)

Potential features you can add:
- [ ] Media messages (images, videos, documents)
- [ ] Message templates
- [ ] Auto-replies/chatbots
- [ ] Analytics dashboard
- [ ] Multiple admin users
- [ ] Export conversations
- [ ] Quick replies/shortcuts
- [ ] Message scheduling
- [ ] Contact tags/labels
- [ ] Notification sounds

## 📞 Getting WhatsApp API Access

1. Create Facebook Business Manager account
2. Add WhatsApp Business product
3. Verify your business
4. Complete phone number verification
5. Get API credentials

**Note:** This can take several days for approval.

## 💡 Pro Tips

- **Backup regularly** - Schedule automatic database backups
- **Monitor logs** - Check error.log daily
- **Test webhook** - Use Facebook's webhook tester
- **Rate limits** - Respect WhatsApp's messaging limits
- **Customer service** - Respond promptly to build trust

## 📧 Support Contacts

- **WhatsApp API:** Facebook Developer Support
- **Hosting:** Namecheap Support
- **PHP/MySQL:** Community forums

---

**Need help? Check [SETUP_GUIDE.md](SETUP_GUIDE.md) for detailed instructions!**
