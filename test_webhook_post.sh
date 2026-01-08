#!/bin/bash
# Test webhook with real WhatsApp payload

curl -X POST https://whatsapp.nexofydigital.com/webhook.php \
  -H "Content-Type: application/json" \
  -d '{
  "object": "whatsapp_business_account",
  "entry": [
    {
      "id": "1082072684004260",
      "changes": [
        {
          "value": {
            "messaging_product": "whatsapp",
            "metadata": {
              "display_phone_number": "15551369890",
              "phone_number_id": "950744781454427"
            },
            "contacts": [
              {
                "profile": {
                  "name": "Test User"
                },
                "wa_id": "923462115115"
              }
            ],
            "messages": [
              {
                "from": "923462115115",
                "id": "wamid.TEST123456",
                "timestamp": "1704748800",
                "text": {
                  "body": "Test message from script"
                },
                "type": "text"
              }
            ]
          },
          "field": "messages"
        }
      ]
    }
  ]
}'

echo -e "\n\nNow check the logs:"
echo "tail -50 storage/logs/webhook_debug.log"
echo "tail -50 storage/logs/app.log"
