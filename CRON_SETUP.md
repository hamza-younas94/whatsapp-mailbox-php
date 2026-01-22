# Cron Job Setup Guide

## ❌ Current Issue

Your cron is calling `process_jobs.php` via HTTP (wget/curl), which causes:
```
Status: 301 Moved Permanently
Location: https:///
```

This happens because the script is being accessed as a web page, triggering HTTPS redirects with an empty HTTP_HOST.

## ✅ Correct Setup

`process_jobs.php` **MUST** be run via PHP CLI, not HTTP.

### Step 1: Remove Old Cron Job

```bash
crontab -e
```

**Remove any lines like:**
```bash
# WRONG - DO NOT USE
* * * * * wget -O - https://whatsapp.nexofydigital.com/process_jobs.php
* * * * * curl https://whatsapp.nexofydigital.com/process_jobs.php
```

### Step 2: Add Correct Cron Job

Add this line instead:

```bash
* * * * * cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com && /usr/bin/php process_jobs.php >> /home/pakmfguk/logs/cron.log 2>&1
```

**Breakdown:**
- `* * * * *` - Run every minute
- `cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com` - Change to app directory
- `&&` - Then run the next command
- `/usr/bin/php` - PHP CLI binary (find with `which php`)
- `process_jobs.php` - The script to run
- `>> /home/pakmfguk/logs/cron.log 2>&1` - Append output to log file

### Step 3: Verify PHP Path

Find your PHP CLI path:
```bash
which php
# or
which php8.1
```

Common paths:
- `/usr/bin/php`
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php81/root/usr/bin/php` (cPanel)

### Step 4: Test Manually

Run the script manually first:
```bash
cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com
php process_jobs.php
```

You should see:
```
[2026-01-22 10:30:00] Job processor started
✅ Scheduled messages enqueued: 0
✅ Broadcast recipients enqueued: 0
✅ Job queue processed: 0 items
✅ Drip campaigns processed: 0 subscribers
✅ Webhook retries processed: 0
[2026-01-22 10:30:00] Job processor completed
```

### Step 5: Check Cron Logs

After setting up cron, wait 1 minute then check:
```bash
cat /home/pakmfguk/logs/cron.log
```

**You should see job processor output, NOT HTTP redirect errors.**

## 🔧 Alternative: cPanel Cron Jobs

If using cPanel:

1. Login to cPanel
2. Go to **Cron Jobs**
3. Set **Common Settings**: Every Minute (`* * * * *`)
4. **Command**:
   ```bash
   cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com && /usr/bin/php process_jobs.php >> /home/pakmfguk/logs/cron.log 2>&1
   ```
5. Click **Add New Cron Job**

## 📋 What process_jobs.php Does

- ✅ Processes scheduled messages
- ✅ Sends broadcast messages
- ✅ Processes drip campaign steps
- ✅ Retries failed webhooks
- ✅ Runs job queue

**Runs every minute automatically via cron.**

## ⚠️ Important Notes

1. **DO NOT** call `process_jobs.php` via browser/HTTP
2. **DO NOT** use wget/curl in cron
3. **ALWAYS** use PHP CLI: `php process_jobs.php`
4. Script will exit with error if called via HTTP (security measure)

## 🛠️ Troubleshooting

### "Command not found: php"
Use full path: `/usr/bin/php` or `/opt/cpanel/ea-php81/root/usr/bin/php`

### "Permission denied"
Make sure script is readable:
```bash
chmod +x process_jobs.php
```

### "WhatsApp credentials not configured"
Check `.env` file has:
```
WHATSAPP_PHONE_NUMBER_ID=your_phone_id
WHATSAPP_ACCESS_TOKEN=your_token
```

### Still getting 301 errors?
You're still calling via HTTP. **Use PHP CLI only.**

## ✅ Success Indicators

When working correctly, cron.log shows:
```
[2026-01-22 10:30:00] Job processor started
✅ Scheduled messages enqueued: X
✅ Broadcast recipients enqueued: Y
✅ Job queue processed: Z items
[2026-01-22 10:30:00] Job processor completed
```

**No HTTP status codes, no redirects.**

## 🚀 Quick Fix Command

```bash
# Remove all cron jobs
crontab -r

# Add correct cron
(crontab -l 2>/dev/null; echo "* * * * * cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com && /usr/bin/php process_jobs.php >> /home/pakmfguk/logs/cron.log 2>&1") | crontab -

# Verify
crontab -l
```

---

**After fixing, pull latest code:**
```bash
cd /home/pakmfguk/public_html/whatsapp.nexofydigital.com
git pull origin main
```

This update adds the HTTP check to prevent the redirect error.
