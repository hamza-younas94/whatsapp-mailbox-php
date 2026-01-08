<?php
/**
 * Test Webhook Processing Locally
 */

define('NO_SESSION', true);
require_once __DIR__ . '/bootstrap.php';

use App\Services\WhatsAppService;

// Test payload from Facebook
$testPayload = '{
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
                  "name": "Hamza"
                },
                "wa_id": "923462115115"
              }
            ],
            "messages": [
              {
                "from": "923462115115",
                "id": "wamid.HBgMOTIzNDYyMTE1MTE1FQIAEhggQUMxNUYwMUI2OUFCN0REMjhFNjc2ODkzMjZGRTcxQUIA",
                "timestamp": "1767902090",
                "text": {
                  "body": "Okay"
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
}';

echo "Testing webhook processing...\n\n";

try {
    $data = json_decode($testPayload, true);
    echo "✓ JSON decoded successfully\n";
    
    $whatsappService = new WhatsAppService();
    echo "✓ WhatsAppService initialized\n\n";
    
    if (isset($data['entry'])) {
        echo "Processing " . count($data['entry']) . " entry/entries...\n";
        
        foreach ($data['entry'] as $entry) {
            if (isset($entry['changes'])) {
                echo "  Found " . count($entry['changes']) . " change(s)\n";
                
                foreach ($entry['changes'] as $change) {
                    echo "  Field: " . ($change['field'] ?? 'unknown') . "\n";
                    
                    if ($change['field'] === 'messages') {
                        echo "  Processing message...\n";
                        $result = $whatsappService->processWebhookMessage($change['value']);
                        echo "  Result: " . ($result ? '✓ Success' : '✗ Failed') . "\n";
                    }
                }
            }
        }
    }
    
    echo "\n";
    
    // Check if message was saved
    use App\Models\Message;
    use App\Models\Contact;
    
    $contact = Contact::where('phone_number', '923462115115')->first();
    if ($contact) {
        echo "✓ Contact created/found: {$contact->name} ({$contact->phone_number})\n";
        echo "  Contact ID: {$contact->id}\n";
        echo "  Messages count: " . $contact->messages()->count() . "\n";
        
        $latestMessage = $contact->messages()->latest('timestamp')->first();
        if ($latestMessage) {
            echo "  Latest message: {$latestMessage->message_body}\n";
        }
    } else {
        echo "✗ Contact not found in database\n";
    }
    
    echo "\nTest completed!\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
