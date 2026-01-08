#!/bin/bash

echo "==================================="
echo "Testing Raw Logger Webhook"
echo "==================================="
echo ""

echo "1. Testing GET verification..."
VERIFY_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" \
  "https://whatsapp.nexofydigital.com/webhook_raw_logger.php?hub_mode=subscribe&hub_verify_token=apple&hub_challenge=TEST123")

echo "Response:"
echo "$VERIFY_RESPONSE"
echo ""

echo "2. Testing POST message..."
POST_RESPONSE=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST \
  "https://whatsapp.nexofydigital.com/webhook_raw_logger.php" \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{
      "id": "TEST_ENTRY",
      "changes": [{
        "field": "messages",
        "value": {
          "messaging_product": "whatsapp",
          "messages": [{
            "from": "923462115115",
            "id": "wamid.TEST123",
            "timestamp": "'$(date +%s)'",
            "type": "text",
            "text": {
              "body": "Test message from script"
            }
          }]
        }
      }]
    }]
  }')

echo "Response:"
echo "$POST_RESPONSE"
echo ""

echo "3. Checking if log file was created..."
if [ -f storage/logs/webhook_raw_$(date +%Y-%m-%d).log ]; then
    echo "✓ Log file exists!"
    echo ""
    echo "Log contents:"
    cat storage/logs/webhook_raw_$(date +%Y-%m-%d).log
else
    echo "✗ Log file NOT created"
    echo "This means webhook_raw_logger.php is not executing properly"
fi

echo ""
echo "==================================="
echo "NEXT STEPS"
echo "==================================="
echo "If log file was created:"
echo "  → Raw logger is working"
echo "  → Change Facebook webhook URL to: https://whatsapp.nexofydigital.com/webhook_raw_logger.php"
echo "  → Send test message from 923462115115"
echo ""
echo "If log file was NOT created:"
echo "  → Check file permissions: ls -la webhook_raw_logger.php"
echo "  → Check PHP errors: tail -20 error_log"
