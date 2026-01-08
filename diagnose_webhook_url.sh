#!/bin/bash
echo "=== Webhook URL Diagnostic ==="
echo ""
echo "1. Testing if webhook URL is accessible:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://whatsapp.nexofydigital.com/webhook.php

echo ""
echo "2. Testing webhook with GET (verification):"
curl -s "https://whatsapp.nexofydigital.com/webhook.php?hub_mode=subscribe&hub_verify_token=apple&hub_challenge=test123"

echo ""
echo ""
echo "3. Testing webhook with POST (message simulation):"
curl -X POST https://whatsapp.nexofydigital.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
  "object": "whatsapp_business_account",
  "entry": [{
    "id": "1082072684004260",
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "metadata": {
          "display_phone_number": "15551369890",
          "phone_number_id": "950744781454427"
        },
        "contacts": [{
          "profile": {"name": "Test Reply"},
          "wa_id": "923462115115"
        }],
        "messages": [{
          "from": "923462115115",
          "id": "wamid.TEST_REPLY_123",
          "timestamp": "1704750000",
          "text": {"body": "This is a reply test"},
          "type": "text"
        }]
      },
      "field": "messages"
    }]
  }]
}'

echo ""
echo ""
echo "4. Check recent webhook activity:"
echo "Last 3 webhook calls in logs:"
grep "Webhook called at" storage/logs/app.log | tail -3

echo ""
echo ""
echo "=== What to check in Facebook Developer Console ==="
echo "1. Go to: Configuration > Webhooks"
echo "2. Verify Callback URL is EXACTLY: https://whatsapp.nexofydigital.com/webhook.php"
echo "3. Verify token is: apple"
echo "4. Make sure 'messages' field is SUBSCRIBED (blue toggle)"
echo ""
echo "=== Phone Number Check ==="
echo "5. Is 923462115115 in your 'Recipient phone numbers' list?"
echo "   (Development mode only allows messages from allowed numbers)"
echo ""
echo "=== If webhook URL is correct but still not working ==="
echo "6. Try unsubscribing and re-subscribing to 'messages' field"
echo "7. Wait 2-3 minutes for Facebook to update"
echo "8. Send another test reply"
