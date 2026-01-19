<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Models\Message;
use App\Models\Contact;
use App\Models\UserApiCredential;
use App\Middleware\TenantContext;

/**
 * Multi-Tenant WhatsApp Service
 * 
 * Uses user-specific API credentials instead of global .env settings
 */
class MultiTenantWhatsAppService
{
    private $client;
    private $userId;
    private $credentials;

    /**
     * Create service for specific user (tenant)
     */
    public function __construct($userId = null)
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true
        ]);
        
        // Use provided user ID or get from tenant context
        $this->userId = $userId ?? TenantContext::getUserId();
        
        if (!$this->userId) {
            throw new \Exception("No user context set. Cannot initialize WhatsApp service.");
        }
        
        // Load user's API credentials
        $this->credentials = UserApiCredential::where('user_id', $this->userId)
            ->where('is_active', true)
            ->first();
            
        if (!$this->credentials) {
            throw new \Exception("No active API credentials found for user {$this->userId}");
        }
    }
    
    /**
     * Get access token for this user
     */
    private function getAccessToken()
    {
        return $this->credentials->api_access_token;
    }
    
    /**
     * Get phone number ID for this user
     */
    private function getPhoneNumberId()
    {
        return $this->credentials->api_phone_number_id;
    }
    
    /**
     * Get API version for this user
     */
    private function getApiVersion()
    {
        return $this->credentials->api_version;
    }
    
    /**
     * Get verify token for this user
     */
    public function getVerifyToken()
    {
        return $this->credentials->webhook_verify_token;
    }
    
    /**
     * Create service instance from phone number ID (for webhook routing)
     */
    public static function fromPhoneNumberId($phoneNumberId)
    {
        $credentials = UserApiCredential::findByPhoneNumberId($phoneNumberId);
        
        if (!$credentials) {
            throw new \Exception("No credentials found for phone number ID: {$phoneNumberId}");
        }
        
        return new self($credentials->user_id);
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
        // Check subscription limits
        $subscription = TenantContext::getSubscription();
        if ($subscription && !$subscription->canSendMessage()) {
            return [
                'success' => false,
                'error' => 'Message limit reached. Please upgrade your plan.'
            ];
        }
        
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        $url = "https://graph.facebook.com/{$this->getApiVersion()}/{$this->getPhoneNumberId()}/messages";

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
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json'
                ],
                'json' => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['messages'][0]['id'])) {
                // Increment usage
                if ($subscription) {
                    $subscription->incrementMessageUsage();
                }
                
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
     * 
     * IMPORTANT: Sets tenant context before processing
     */
    public function processWebhookMessage($value)
    {
        try {
            // Set tenant context for this request
            TenantContext::setUser($this->userId);
            
            logger('[SERVICE] processWebhookMessage called for user ' . $this->userId);
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

            // Record webhook activity
            $this->credentials->recordWebhook();
            
            logger('[SERVICE] processWebhookMessage completed successfully');
            return true;
        } catch (\Exception $e) {
            logger('[SERVICE ERROR] Error processing webhook: ' . $e->getMessage(), 'error');
            logger('[SERVICE ERROR] Stack trace: ' . $e->getTraceAsString(), 'error');
            return false;
        }
    }
    
    /**
     * Save incoming message to database with user_id
     */
    private function saveIncomingMessage($messageData, $webhookValue)
    {
        $messageId = $messageData['id'];
        $from = $messageData['from'];
        $timestamp = date('Y-m-d H:i:s', $messageData['timestamp']);
        
        logger("[SAVE] Starting saveIncomingMessage: MessageID={$messageId}, From={$from}, Timestamp={$timestamp}");

        // Get or create contact FOR THIS USER
        logger("[SAVE] Getting or creating contact for: {$from}");
        $contact = $this->getOrCreateContact($from, $webhookValue);
        logger("[SAVE] Contact resolved: ID=" . $contact->id . ", Name=" . $contact->name);

        // Extract message content based on type
        $messageType = $messageData['type'];
        $messageBody = '';
        
        logger("[SAVE] Message type: {$messageType}");

        if ($messageType === 'text') {
            $messageBody = $messageData['text']['body'] ?? '';
        }

        // Save message WITH user_id
        logger("[SAVE] Saving message to database: Body=" . substr($messageBody, 0, 50));
        
        $message = Message::updateOrCreate(
            ['message_id' => $messageId],
            [
                'user_id' => $this->userId, // TENANT ISOLATION
                'contact_id' => $contact->id,
                'phone_number' => $from,
                'message_type' => $messageType,
                'direction' => 'incoming',
                'message_body' => $messageBody,
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
        
        // Check for quick reply shortcuts (only for text messages)
        if ($messageType === 'text' && !empty($messageBody)) {
            $this->checkAndSendQuickReply($messageBody, $contact, $from);
        }
    }
    
    /**
     * Get or create contact FOR THIS USER (tenant isolation)
     */
    private function getOrCreateContact($phoneNumber, $webhookValue)
    {
        logger("[CONTACT] Looking up contact: {$phoneNumber}");
        
        // Find contact for THIS USER only
        $contact = Contact::where('phone_number', $phoneNumber)
            ->where('user_id', $this->userId) // TENANT FILTER
            ->first();

        if (!$contact) {
            $name = $webhookValue['contacts'][0]['profile']['name'] ?? $phoneNumber;
            logger("[CONTACT] Contact not found. Creating new contact: {$name}");

            $contact = Contact::create([
                'user_id' => $this->userId, // TENANT OWNERSHIP
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
     * Check and send quick reply (tenant-aware)
     */
    private function checkAndSendQuickReply($messageBody, $contact, $phoneNumber)
    {
        try {
            logger("[QUICK_REPLY] Checking message: " . substr($messageBody, 0, 50));
            
            $messageBody = trim($messageBody);
            if (empty($messageBody)) {
                logger("[QUICK_REPLY] Empty message, skipping");
                return;
            }
            
            $searchText = strtolower($messageBody);
            
            // Get active quick replies FOR THIS USER
            $allQuickReplies = \App\Models\QuickReply::where('user_id', $this->userId)
                ->where('is_active', true)
                ->orderBy('usage_count', 'desc')
                ->get();
            
            logger("[QUICK_REPLY] Found " . $allQuickReplies->count() . " active quick replies for user {$this->userId}");
            
            if ($allQuickReplies->isEmpty()) {
                logger("[QUICK_REPLY] No active quick replies found");
                return;
            }
            
            // Match logic (same as before)
            $matchedReply = null;
            
            foreach ($allQuickReplies as $qr) {
                $shortcut = trim($qr->shortcut);
                $shortcutLower = strtolower($shortcut);
                $shortcutNoSlash = ltrim($shortcutLower, '/');
                
                if ($searchText === $shortcutLower || $searchText === $shortcutNoSlash) {
                    $matchedReply = $qr;
                    break;
                }
            }
            
            if ($matchedReply) {
                logger("[QUICK_REPLY] Match found: '{$matchedReply->title}'");
                
                $response = $this->sendTextMessage($phoneNumber, $matchedReply->message);
                
                if ($response['success']) {
                    $matchedReply->increment('usage_count');
                    
                    // Save outgoing message WITH user_id
                    Message::create([
                        'user_id' => $this->userId,
                        'contact_id' => $contact->id,
                        'phone_number' => $phoneNumber,
                        'message_id' => $response['message_id'],
                        'message_type' => 'text',
                        'direction' => 'outgoing',
                        'message_body' => '[AUTO-REPLY] ' . $matchedReply->message,
                        'timestamp' => now(),
                        'status' => 'sent',
                        'is_read' => true
                    ]);
                    
                    logger("[QUICK_REPLY] ✅ Sent successfully");
                }
            }
        } catch (\Exception $e) {
            logger("[QUICK_REPLY ERROR] " . $e->getMessage(), 'error');
        }
    }
    
    private function updateMessageStatus($status)
    {
        $messageId = $status['id'];
        $newStatus = $status['status'];

        // Update message for THIS USER only
        $updated = Message::where('message_id', $messageId)
            ->where('user_id', $this->userId) // TENANT FILTER
            ->update(['status' => $newStatus]);
            
        if ($updated) {
            logger("[STATUS] Updated message {$messageId} to status: {$newStatus}");
        }
    }
}
