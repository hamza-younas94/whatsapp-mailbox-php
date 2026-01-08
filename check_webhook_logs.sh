#!/bin/bash
echo "Checking webhook logs for recent activity..."
echo ""
echo "=== Recent webhook debug logs ==="
if [ -f storage/logs/webhook_debug.log ]; then
    tail -100 storage/logs/webhook_debug.log | grep "2026-01-09 01:2"
    if [ $? -ne 0 ]; then
        echo "No logs found for timestamp 01:26-01:27"
        echo "Last 5 webhook requests:"
        tail -50 storage/logs/webhook_debug.log | grep "WEBHOOK REQUEST" | tail -5
    fi
else
    echo "webhook_debug.log does not exist"
fi

echo ""
echo "=== Recent app logs ==="
tail -100 storage/logs/app.log | grep "2026-01-09 01:2"
if [ $? -ne 0 ]; then
    echo "No logs for 01:26-01:27 timeframe"
    echo "Last 10 log entries:"
    tail -10 storage/logs/app.log
fi

echo ""
echo "=== Check if webhook.php is webhook_debug.php ==="
head -5 webhook.php | grep "Webhook Debug"
if [ $? -eq 0 ]; then
    echo "✓ Using debug version"
else
    echo "✗ Using regular version (no detailed logging)"
fi

echo ""
echo "=== Apache/Nginx error logs (if accessible) ==="
if [ -f /var/log/httpd/error_log ]; then
    tail -20 /var/log/httpd/error_log | grep -i "webhook\|whatsapp"
elif [ -f /var/log/apache2/error.log ]; then
    tail -20 /var/log/apache2/error.log | grep -i "webhook\|whatsapp"
elif [ -f error_log ]; then
    tail -20 error_log | grep -i "webhook\|whatsapp"
else
    echo "Error log not found"
fi
