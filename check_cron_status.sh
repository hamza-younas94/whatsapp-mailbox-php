#!/bin/bash
# Check Cron Job Status and Setup

echo "==================================="
echo "Cron Job Status Check"
echo "==================================="
echo ""

echo "1. Current User Cron Jobs:"
echo "-----------------------------------"
crontab -l 2>&1 | grep -v "no crontab for"
if [ $? -ne 0 ]; then
    echo "❌ No cron jobs found for current user"
else
    echo "✅ Cron jobs listed above"
fi
echo ""

echo "2. Check if cron service is running:"
echo "-----------------------------------"
ps aux | grep cron | grep -v grep
echo ""

echo "3. Check process_jobs.php file:"
echo "-----------------------------------"
if [ -f "process_jobs.php" ]; then
    echo "✅ process_jobs.php exists"
    ls -la process_jobs.php
    echo ""
    echo "File is executable: $(test -x process_jobs.php && echo 'YES' || echo 'NO')"
else
    echo "❌ process_jobs.php NOT FOUND"
fi
echo ""

echo "4. Test run process_jobs.php manually:"
echo "-----------------------------------"
php process_jobs.php
echo ""

echo "5. Check logs directory:"
echo "-----------------------------------"
if [ -d "storage/logs" ]; then
    echo "✅ Logs directory exists"
    ls -la storage/logs/
else
    echo "❌ Logs directory missing - creating..."
    mkdir -p storage/logs
    chmod 755 storage/logs
fi
echo ""

echo "6. Recent log entries (last 20 lines):"
echo "-----------------------------------"
if [ -f "storage/logs/app.log" ]; then
    tail -20 storage/logs/app.log
else
    echo "❌ No app.log file found"
fi
echo ""

echo "==================================="
echo "Setup Instructions:"
echo "==================================="
echo ""
echo "To add cron job, run:"
echo "crontab -e"
echo ""
echo "Then add this line:"
echo "* * * * * cd $(pwd) && php process_jobs.php >> storage/logs/cron.log 2>&1"
echo ""
echo "This will run every minute."
echo ""
echo "To verify it's added:"
echo "crontab -l"
