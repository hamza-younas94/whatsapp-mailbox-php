#!/bin/bash

echo "==================================="
echo "Checking Server Access Logs"
echo "==================================="
echo ""

echo "--- Last 20 requests to webhook.php ---"
if [ -f ~/access-logs/whatsapp.nexofydigital.com ]; then
    grep "webhook.php" ~/access-logs/whatsapp.nexofydigital.com | tail -20
elif [ -f /var/log/apache2/access.log ]; then
    sudo grep "webhook.php" /var/log/apache2/access.log | tail -20
elif [ -f /var/log/httpd/access_log ]; then
    sudo grep "webhook.php" /var/log/httpd/access_log | tail -20
else
    echo "⚠️  Cannot find access logs. Common locations:"
    echo "   - ~/access-logs/whatsapp.nexofydigital.com"
    echo "   - /var/log/apache2/access.log"
    echo "   - /var/log/httpd/access_log"
fi

echo ""
echo "--- Facebook User-Agent requests (last 10) ---"
if [ -f ~/access-logs/whatsapp.nexofydigital.com ]; then
    grep -i "facebookexternalhit\|whatsapp" ~/access-logs/whatsapp.nexofydigital.com | tail -10
elif [ -f /var/log/apache2/access.log ]; then
    sudo grep -i "facebookexternalhit\|whatsapp" /var/log/apache2/access.log | tail -10
elif [ -f /var/log/httpd/access_log ]; then
    sudo grep -i "facebookexternalhit\|whatsapp" /var/log/httpd/access_log | tail -10
fi

echo ""
echo "--- Recent webhook.php errors (if any) ---"
if [ -f ~/error-logs/whatsapp.nexofydigital.com ]; then
    grep "webhook.php" ~/error-logs/whatsapp.nexofydigital.com | tail -10
elif [ -f /var/log/apache2/error.log ]; then
    sudo grep "webhook.php" /var/log/apache2/error.log | tail -10
elif [ -f /var/log/httpd/error_log ]; then
    sudo grep "webhook.php" /var/log/httpd/error_log | tail -10
fi

echo ""
echo "==================================="
echo "Quick Webhook Test"
echo "==================================="
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  "https://whatsapp.nexofydigital.com/webhook.php?hub_mode=subscribe&hub_verify_token=apple&hub_challenge=test123"

echo ""
echo "==================================="
echo "Application Log (last 30 lines)"
echo "==================================="
tail -30 storage/logs/app.log

echo ""
echo "==================================="
echo "CRITICAL CHECKS"
echo "==================================="
echo "1. Did you see ANY recent webhook.php requests in access logs?"
echo "2. Is Facebook's User-Agent appearing in logs?"
echo "3. If NO requests → Facebook isn't calling your webhook"
echo "4. If YES requests but no app logs → PHP error preventing logging"
echo ""
echo "NEXT STEPS IF NO REQUESTS:"
echo "→ Facebook webhook Callback URL is wrong or subscription failed"
echo "→ Completely DELETE webhook subscription in Facebook"
echo "→ Add it again with: https://whatsapp.nexofydigital.com/webhook.php"
echo "→ Use token: apple"
echo "→ Subscribe to 'messages' field"
