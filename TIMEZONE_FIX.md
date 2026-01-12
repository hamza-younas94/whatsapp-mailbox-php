# Timezone Configuration Fix

## Problem
The server runs in **EST (Eastern Standard Time)**, but the application needs to use **Pakistan Standard Time (PKT)** for scheduling messages and broadcasts.

## Solution

### 1. Update .env File on Production Server
SSH into your server and add this to your `.env` file:

```bash
# SSH into server
ssh pakmfguk@premium909

# Navigate to your app directory
cd /home/pakmfguk/whatsapp.nexofydigital.com

# Add timezone setting
echo "" >> .env
echo "# Timezone Configuration" >> .env
echo "TIMEZONE=Asia/Karachi" >> .env
```

### 2. Verify the Change
After deployment, the cron logs should show Pakistan time:

**Before:**
```
Server time: Mon Jan 12 15:03:54 EST 2026 (3:03 PM EST)
Log time: [2026-01-12 20:03:01] (Wrong - 5 hours off)
```

**After:**
```
Server time: Mon Jan 12 15:03:54 EST 2026 (3:03 PM EST)
Log time: [2026-01-13 01:03:54] (Correct - Pakistan time)
```

## Timezone Details
- **Pakistan Standard Time (PKT):** UTC+5
- **Eastern Standard Time (EST):** UTC-5
- **Time difference:** 10 hours (Pakistan is ahead)

## Files Updated
1. `.env` - Added `TIMEZONE=Asia/Karachi`
2. `.env.example` - Updated default timezone
3. `bootstrap.php` - Already reads timezone from env (line 35)

## When This Affects
- ✅ Scheduled Messages - Will send at correct Pakistan time
- ✅ Broadcasts - Will schedule at correct Pakistan time
- ✅ Cron Jobs - Will process at correct time
- ✅ Database timestamps - Will save in Pakistan time
- ✅ All date/time displays in UI

## Testing
1. Deploy the changes
2. Create a scheduled message for 5 minutes from now (Pakistan time)
3. Check cron logs - should show correct Pakistan time
4. Message should send at the scheduled time

## Example
If you schedule a message for **2:00 AM Pakistan time**:
- Server time might be **4:00 PM EST (previous day)**
- But cron will correctly send at **2:00 AM Pakistan time**
- Because the app converts everything to Asia/Karachi timezone

## Deployment Command
```bash
cd /home/pakmfguk/whatsapp.nexofydigital.com
git pull origin main
# Make sure .env has TIMEZONE=Asia/Karachi
```
