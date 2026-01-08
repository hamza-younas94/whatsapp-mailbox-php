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
     * Send a text message
     */
    public function sendTextMessage($to, $message)
    {
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
     * Process incoming webhook message
     */
    public function processWebhookMessage($value)
    {
        try {
            // Process incoming messages
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $messageData) {
                    $this->saveIncomingMessage($messageData, $value);
                }
            }

            // Process message status updates
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $this->updateMessageStatus($status);
                }
            }

            return true;
        } catch (\Exception $e) {
            logger('Error processing webhook: ' . $e->getMessage(), 'error');
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

        // Get or create contact
        $contact = $this->getOrCreateContact($from, $webhookValue);

        // Extract message content based on type
        $messageType = $messageData['type'];
        $messageBody = '';
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaCaption = null;

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
        Message::updateOrCreate(
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

        // Update contact
        $contact->incrementUnread();
        $contact->update(['last_message_time' => $timestamp]);
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
        $contact = Contact::where('phone_number', $phoneNumber)->first();

        if (!$contact) {
            $name = $webhookValue['contacts'][0]['profile']['name'] ?? $phoneNumber;

            $contact = Contact::create([
                'phone_number' => $phoneNumber,
                'name' => $name,
                'last_message_time' => now()
            ]);
        }

        return $contact;
    }

    /**
     * Verify webhook token
     */
    public function verifyWebhook($mode, $token, $challenge)
    {
        $verifyToken = env('WEBHOOK_VERIFY_TOKEN');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }

        return false;
    }
}
