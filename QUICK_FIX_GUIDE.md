# Quick Fix Guide

## ✅ Fixed Issues

### 1. Notes & Deals Page Errors - FIXED
**Error:** `Call to undefined function getAuthenticatedUser()`
**Fix:** Changed to `getCurrentUser()` in both files

### 2. Message Limit API Error - FIXED  
**Error:** Internal server error on `/api.php/message-limit`
**Fix:** Added auto-creation of config values + error handling

### 3. Template Language Codes - UPDATED
Changed to English-only variants (WhatsApp requirement)

## 🚀 On Your Server - Run These Commands

```bash
cd /home/pakmfguk/whatsapp.nexofydigital.com
git pull origin main
php init_config.php  # Initialize config values
rm -rf storage/cache/twig/*
```

**Expected Output:**
```
Initializing config values...
✓ Created message_limit config (500)
✓ Created messages_sent_count config (0)

Current config values:
  - message_limit: 500
  - messages_sent_count: 0

✓ Config initialization complete!
```

## 🔧 Template Message Issue

**Error:** `(#100) Invalid parameter`

**Root Cause:** The template "hello_world" or language code might not be approved for your WhatsApp Business account.

**Solution:**

### Option 1: Use Your Approved Template
1. Go to: https://business.facebook.com/wa/manage/message-templates/
2. Log in with your business account
3. Find your approved templates
4. Use the exact template name in the modal

### Option 2: Create a New Template
1. Go to WhatsApp Business Manager
2. Click "Message Templates"
3. Click "Create Template"
4. Fill in:
   - **Template Name:** e.g., `customer_greeting`
   - **Category:** Utility or Marketing
   - **Language:** English
   - **Header:** (optional) "Hello!"
   - **Body:** "Hi {{1}}, thank you for contacting us!"
   - **Footer:** (optional) Your business name
5. Submit for approval (takes 1-2 days)
6. Once approved, use exact template name in the app

### Common Template Names:
- `hello_world` (default, may not be available)
- `customer_greeting`
- `appointment_reminder`
- `order_confirmation`

### Testing Template:
```json
POST /api.php/send-template
{
  "to": "923171770981",
  "template_name": "YOUR_APPROVED_TEMPLATE_NAME",
  "language_code": "en",
  "contact_id": 1
}
```

## 📋 Verification Checklist

After running the commands above:

- [ ] Notes page loads: `whatsapp.nexofydigital.com/notes.php`
- [ ] Deals page loads: `whatsapp.nexofydigital.com/deals.php`
- [ ] Message counter shows: "500 / 500 messages" (green badge)
- [ ] Template modal opens (click 💬 button)
- [ ] Use your actual approved template name for testing

## 💡 Pro Tips

1. **Find Your Templates:** WhatsApp Business Manager → Message Templates → View all approved templates
2. **Language Codes:** Use `en` for English templates (most common)
3. **Template Names:** Must match exactly (case-sensitive, no spaces)
4. **Parameters:** If template has {{1}}, {{2}}, you need to pass parameters in API call
5. **Testing:** Start with simple templates without parameters first

## 🆘 Still Having Issues?

1. Check your WhatsApp Business account has approved templates
2. Verify Phone Number ID: `868951479645170` is correct
3. Check Access Token is valid in `.env` file
4. Look at error logs: `tail -f storage/logs/error.log`
5. Test with API directly using curl or Postman first

## 📞 Template API Call Example

**With Parameters:**
```php
$whatsappService->sendTemplateMessage(
    '923171770981',
    'customer_greeting',
    'en',
    ['John Doe']  // {{1}} parameter
);
```

**Without Parameters:**
```php
$whatsappService->sendTemplateMessage(
    '923171770981',
    'hello_world',
    'en'
);
```
