# Phase 1 Deployment Troubleshooting Guide

## 🚨 Issue: Can't See Phase 1 Features on Live Server

You've implemented Phase 1 (media upload, notifications, auto-tagging, bulk operations, search) but they're not visible on your live site even after clearing cache.

## ✅ Solution Steps

### Step 1: Pull Latest Code from GitHub
```bash
cd /path/to/your/whatsapp-mailbox
git pull origin main
```

### Step 2: Clear Server-Side Caches
Visit these URLs on your domain:
1. **Clear all caches:** `https://yourdomain.com/clear-cache.php`
2. **Run diagnostics:** `https://yourdomain.com/check-media.php`

### Step 3: Clear Browser Cache
**Option A - Hard Refresh:**
- Windows/Linux: `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

**Option B - Clear Browser Cache:**
1. Chrome: Settings → Privacy and security → Clear browsing data
2. Select "Cached images and files"
3. Click "Clear data"

**Option C - Use Incognito Mode:**
- Open a new incognito/private window
- Visit your site fresh

### Step 4: Check for JavaScript Errors
1. Press `F12` to open Developer Tools
2. Go to **Console** tab
3. Look for any red error messages
4. Take a screenshot if you see errors

### Step 5: Verify Files on Server
Check if these files exist and are updated:
- `/assets/js/app.js` - Should contain `handleFileSelect` function
- `/assets/css/style.css` - Should contain `.media-preview` styles
- `/api.php` - Should have `case 'send-media':` endpoint
- `/app/Services/WhatsAppService.php` - Should have `sendMediaMessage` method

### Step 6: Check .htaccess
The `.htaccess` file was caching CSS/JS for 1 month. This has been fixed. Ensure your `.htaccess` contains:

```apache
# Force no caching for CSS and JavaScript files
<FilesMatch "\.(css|js)$">
    <IfModule mod_headers.c>
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Pragma "no-cache"
        Header set Expires 0
    </IfModule>
</FilesMatch>
```

### Step 7: Verify Uploads Directory
The media upload feature needs a writable `uploads/` directory:

1. Check if `/uploads/` folder exists on your server
2. If not, create it: `mkdir uploads`
3. Set permissions: `chmod 755 uploads`

### Step 8: Run Migrations
Make sure all database tables are created:
1. Visit: `https://yourdomain.com/run-migrations.php`
2. You should see success messages for 8 new tables
3. Check for any errors

## 🔍 What Phase 1 Includes

After deployment, you should see:

### 1. Media Upload (Mailbox Page)
- **Attachment button** (📎 icon) next to message input
- Click to select images, videos, audio, documents
- Preview before sending
- Caption input field

### 2. Desktop Notifications
- Browser permission request on first visit
- Notifications for new messages when tab is inactive
- Click notification to view message

### 3. Auto-Tag Rules (New Page)
- Visit: `https://yourdomain.com/auto-tag-rules.php`
- Navbar: More → (should be added)
- Create rules to auto-assign tags based on keywords

### 4. Bulk Operations (CRM Page)
- Checkboxes next to each contact
- Yellow toolbar appears when selecting contacts
- Bulk tag, bulk stage change, bulk delete

### 5. Advanced Search (New Page)
- Visit: `https://yourdomain.com/search.php`
- Navbar: More → (should be added)
- Filter by stage, tags, date, message type, lead score

## 🐛 Common Issues & Fixes

### Issue: "Attach media" button not visible
**Cause:** CSS not loaded or JavaScript error
**Fix:**
1. Hard refresh (Ctrl+Shift+R)
2. Check browser console for errors
3. Verify `assets/css/style.css` was updated

### Issue: Button visible but file upload doesn't work
**Cause:** JavaScript function missing
**Fix:**
1. Check browser console for errors
2. Verify `assets/js/app.js` contains `handleFileSelect`
3. Clear cache and hard refresh

### Issue: "Failed to send media" error
**Cause:** Uploads directory missing or not writable
**Fix:**
1. Create `uploads/` folder
2. Set permissions: `chmod 755 uploads`
3. Check Apache has write access

### Issue: Media sent but not saved to database
**Cause:** Database columns missing
**Fix:**
1. Visit `/run-migrations.php`
2. Check that migration 011 ran successfully
3. Verify `messages` table has `media_id`, `media_filename`, `media_size` columns

### Issue: Auto-tag rules page shows 404
**Cause:** File not deployed or routing issue
**Fix:**
1. Check if `auto-tag-rules.php` exists on server
2. Visit directly: `/auto-tag-rules.php`
3. Check for .htaccess issues

### Issue: Bulk operations checkboxes not showing
**Cause:** Template not updated
**Fix:**
1. Verify `templates/crm_dashboard.html.twig` was updated
2. Clear Twig cache: delete `/cache/*` files
3. Visit `/clear-cache.php`

## 📊 Verification Checklist

Run through this checklist:

- [ ] Git pulled latest code
- [ ] Visited `/clear-cache.php`
- [ ] Visited `/check-media.php` - all checks pass
- [ ] Hard refreshed browser (Ctrl+Shift+R)
- [ ] No errors in browser console (F12)
- [ ] Attach button visible in mailbox
- [ ] Can select and preview files
- [ ] `/uploads/` directory exists and is writable
- [ ] Migrations completed successfully
- [ ] Auto-tag rules page accessible
- [ ] Bulk checkboxes visible in CRM
- [ ] Search page accessible

## 🆘 Still Not Working?

### Debug Mode
Add this to the top of `index.php` temporarily:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### Check Server Logs
- cPanel: Error Logs section
- SSH: `tail -f /var/log/apache2/error.log`

### Verify PHP Version
Must be PHP 7.4 or higher:
```php
<?php echo phpversion(); ?>
```

### Contact for Help
If still having issues, provide:
1. Screenshot of `/check-media.php` results
2. Browser console errors (F12 → Console tab)
3. Server error logs
4. PHP version
5. Hosting provider (Namecheap, etc.)

## 📁 Complete File List for Phase 1

Files that were added/modified:

### New Files
- `auto-tag-rules.php` - Auto-tag management page
- `search.php` - Advanced search page
- `templates/auto_tag_rules.html.twig` - Auto-tag template
- `templates/search.html.twig` - Search template
- `assets/js/auto-tag-rules.js` - Auto-tag JavaScript
- `assets/js/search.js` - Search JavaScript
- `check-media.php` - Diagnostic tool
- `clear-cache.php` - Cache clearing utility
- `implementation-status.php` - Progress tracker
- 4 migration files (010-013)
- 6 model classes in `app/Models/`

### Modified Files
- `api.php` - Added 10+ new endpoints
- `assets/js/app.js` - Added media handling functions
- `assets/css/style.css` - Added 200+ lines of styles
- `templates/dashboard.html.twig` - Added media upload UI
- `templates/crm_dashboard.html.twig` - Added bulk operations
- `app/Services/WhatsAppService.php` - Added media methods
- `.htaccess` - Fixed caching issues

## 🎯 Expected Behavior

After successful deployment:

1. **Mailbox page:** Attachment button (📎) visible, click to upload media
2. **Browser:** Notification permission requested on first visit
3. **CRM page:** Checkboxes next to contacts for bulk operations
4. **Navbar:** "More" dropdown includes new pages
5. **Search:** Full-text search with advanced filters
6. **Auto-tagging:** Rules automatically apply to new messages

---

**Last Updated:** January 13, 2026
**Phase:** 1 of 3 Complete (50%)
**Next:** Phase 2 - Template Manager, CSV Import, Multi-User System
