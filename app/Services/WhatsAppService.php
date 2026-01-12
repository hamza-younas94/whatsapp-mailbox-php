<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Message;
use App\Models\Contact;

class WhatsAppService
{
    private $client;
    private $accessToken;
    private $phoneNumberId;
    private $apiVersion;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true
        ]);
        
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN');
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->apiVersion = env('WHATSAPP_API_VERSION', 'v18.0');
    }

    /**
     * Format phone number for WhatsApp API
     */
    private function formatPhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading zeros
        $phone = ltrim($phone, '0');
        
        // If doesn't start with country code, assume Pakistan (92)
        if (!str_starts_with($phone, '92') && !str_starts_with($phone, '1')) {
            $phone = '92' . $phone;
        }
        
        logger("[PHONE] Formatted: {$phone}");
        return $phone;
    }

    /**
     * Send a text message
     */
    public function sendTextMessage($to, $message)
    {
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id'],
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to send message',
                'data' => $result
            ];

        } catch (RequestException $e) {
            logger('WhatsApp API Error: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }

    /**
     * Send a template message (for starting conversations)
     */
    public function sendTemplateMessage($to, $templateName, $languageCode = 'en', $parameters = [])
    {
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // Build template payload
        $template = [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode
            ]
        ];

        // Add parameters if provided
        if (!empty($parameters)) {
            $template['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $parameters)
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => $template
        ];

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['messages'][0]['id'])) {
                return [
                    'success' => true,
                    'message_id' => $result['messages'][0]['id'],
                    'data' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to send template message',
                'data' => $result
            ];

        } catch (RequestException $e) {
            logger('WhatsApp Template API Error: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ];
        }
    }

    /**
     * Process incoming webhook message
     */
    public function processWebhookMessage($value)
    {
        try {
            logger('[SERVICE] processWebhookMessage called');
            logger('[SERVICE] Value keys: ' . implode(', ', array_keys($value)));
            
            // Process incoming messages
            if (isset($value['messages'])) {
                $messageCount = count($value['messages']);
                logger("[SERVICE] Found {$messageCount} message(s) to process");
                
                foreach ($value['messages'] as $index => $messageData) {
                    logger("[SERVICE] Processing message #{$index}: ID=" . ($messageData['id'] ?? 'unknown') . ", Type=" . ($messageData['type'] ?? 'unknown'));
                    $this->saveIncomingMessage($messageData, $value);
                    logger("[SERVICE] Message #{$index} saved successfully");
                }
            } else {
                logger('[SERVICE] No messages in webhook value', 'info');
            }

            // Process message status updates
            if (isset($value['statuses'])) {
                $statusCount = count($value['statuses']);
                logger("[SERVICE] Found {$statusCount} status update(s)");
                
                foreach ($value['statuses'] as $status) {
                    logger('[SERVICE] Updating status for message: ' . ($status['id'] ?? 'unknown'));
                    $this->updateMessageStatus($status);
                }
            }

            logger('[SERVICE] processWebhookMessage completed successfully');
            return true;
        } catch (\Exception $e) {
            logger('[SERVICE ERROR] Error processing webhook: ' . $e->getMessage(), 'error');
            logger('[SERVICE ERROR] Stack trace: ' . $e->getTraceAsString(), 'error');
            return false;
        }
    }

    /**
     * Save incoming message to database
     */
    private function saveIncomingMessage($messageData, $webhookValue)
    {
        $messageId = $messageData['id'];
        $from = $messageData['from'];
        $timestamp = date('Y-m-d H:i:s', $messageData['timestamp']);
        
        logger("[SAVE] Starting saveIncomingMessage: MessageID={$messageId}, From={$from}, Timestamp={$timestamp}");

        // Get or create contact
        logger("[SAVE] Getting or creating contact for: {$from}");
        $contact = $this->getOrCreateContact($from, $webhookValue);
        logger("[SAVE] Contact resolved: ID=" . $contact->id . ", Name=" . $contact->name);

        // Extract message content based on type
        $messageType = $messageData['type'];
        $messageBody = '';
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaCaption = null;
        
        logger("[SAVE] Message type: {$messageType}");

        switch ($messageType) {
            case 'text':
                $messageBody = $messageData['text']['body'] ?? '';
                break;

            case 'image':
                $mediaUrl = $messageData['image']['id'] ?? '';
                $mediaMimeType = $messageData['image']['mime_type'] ?? '';
                $mediaCaption = $messageData['image']['caption'] ?? '';
                break;

            case 'audio':
                $mediaUrl = $messageData['audio']['id'] ?? '';
                $mediaMimeType = $messageData['audio']['mime_type'] ?? '';
                break;

            case 'video':
                $mediaUrl = $messageData['video']['id'] ?? '';
                $mediaMimeType = $messageData['video']['mime_type'] ?? '';
                $mediaCaption = $messageData['video']['caption'] ?? '';
                break;

            case 'document':
                $mediaUrl = $messageData['document']['id'] ?? '';
                $mediaMimeType = $messageData['document']['mime_type'] ?? '';
                $mediaCaption = $messageData['document']['filename'] ?? '';
                break;

            case 'location':
                $latitude = $messageData['location']['latitude'] ?? '';
                $longitude = $messageData['location']['longitude'] ?? '';
                $messageBody = "Location: {$latitude}, {$longitude}";
                break;
        }

        // Save message
        logger("[SAVE] Saving message to database: Body=" . substr($messageBody, 0, 50));
        
        $message = Message::updateOrCreate(
            ['message_id' => $messageId],
            [
                'contact_id' => $contact->id,
                'phone_number' => $from,
                'message_type' => $messageType,
                'direction' => 'incoming',
                'message_body' => $messageBody,
                'media_url' => $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'media_caption' => $mediaCaption,
                'timestamp' => $timestamp,
                'is_read' => false
            ]
        );
        
        logger("[SAVE] Message saved with ID: " . $message->id);

        // Update contact
        logger("[SAVE] Updating contact unread count and last message time");
        $contact->incrementUnread();
        $contact->update(['last_message_time' => $timestamp]);
        logger("[SAVE] Contact updated successfully");
    }

    /**
     * Update message status
     */
    private function updateMessageStatus($status)
    {
        $messageId = $status['id'];
        $newStatus = $status['status'];

        Message::where('message_id', $messageId)
            ->update(['status' => $newStatus]);
    }

    /**
     * Get or create contact
     */
    private function getOrCreateContact($phoneNumber, $webhookValue)
    {
        logger("[CONTACT] Looking up contact: {$phoneNumber}");
        $contact = Contact::where('phone_number', $phoneNumber)->first();

        if (!$contact) {
            $name = $webhookValue['contacts'][0]['profile']['name'] ?? $phoneNumber;
            logger("[CONTACT] Contact not found. Creating new contact: {$name}");

            $contact = Contact::create([
                'phone_number' => $phoneNumber,
                'name' => $name,
                'last_message_time' => now()
            ]);
            
            logger("[CONTACT] New contact created with ID: " . $contact->id);
        } else {
            logger("[CONTACT] Existing contact found: ID=" . $contact->id . ", Name=" . $contact->name);
        }

        return $contact;
    }

    /**
     * Verify webhook token
     */
    public function verifyWebhook($mode, $token, $challenge)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN');
        
        logger("[VERIFY] Comparing tokens - Mode: {$mode}, Received: {$token}, Expected: {$verifyToken}");

        if ($mode === 'subscribe' && $token === $verifyToken) {
            logger("[VERIFY] ✓ Token match successful");
            return $challenge;
        }

        logger("[VERIFY] ✗ Token mismatch or invalid mode", 'error');
        return false;
    }
}
