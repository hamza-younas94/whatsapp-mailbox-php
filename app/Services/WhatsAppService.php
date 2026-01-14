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
     * Send media message (image, document, video, audio)
     */
    public function sendMediaMessage($to, $mediaUrl, $mediaType = 'image', $caption = null, $filename = null)
    {
        // Format phone number
        $to = $this->formatPhoneNumber($to);
        
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // Build media payload
        $mediaPayload = [
            'link' => $mediaUrl
        ];
        
        if ($caption && in_array($mediaType, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $caption;
        }
        
        if ($filename && $mediaType === 'document') {
            $mediaPayload['filename'] = $filename;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $mediaType,
            $mediaType => $mediaPayload
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
                'error' => 'Failed to send media',
                'data' => $result
            ];

        } catch (RequestException $e) {
            logger('WhatsApp Media Error: ' . $e->getMessage(), 'error');
            
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
        
        // Validate phone number format (must be digits only, 10-15 digits)
        if (!preg_match('/^\d{10,15}$/', $to)) {
            return [
                'success' => false,
                'error' => 'Invalid phone number format. Must be 10-15 digits.',
                'phone' => $to
            ];
        }
        
        // Normalize language code (convert "en_US" to "en" or keep as is)
        $languageCode = strtolower(trim($languageCode));
        // If language code has underscore, take first part (e.g., "en_US" -> "en")
        if (strpos($languageCode, '_') !== false) {
            $languageCode = explode('_', $languageCode)[0];
        }
        
        // Validate template name (no spaces, alphanumeric and underscores only)
        $templateName = trim($templateName);
        if (empty($templateName) || !preg_match('/^[a-zA-Z0-9_]+$/', $templateName)) {
            return [
                'success' => false,
                'error' => 'Invalid template name. Must contain only letters, numbers, and underscores.',
                'template_name' => $templateName
            ];
        }
        
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // Build template payload - WhatsApp API requires specific format
        $template = [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode
            ]
        ];

        // Add parameters if provided
        if (!empty($parameters) && is_array($parameters)) {
            $template['components'] = [];
            
            // Body parameters
            $bodyParams = [];
            foreach ($parameters as $param) {
                if (is_string($param) && !empty($param)) {
                    $bodyParams[] = [
                        'type' => 'text',
                        'text' => $param
                    ];
                }
            }
            
            if (!empty($bodyParams)) {
                $template['components'][] = [
                    'type' => 'body',
                    'parameters' => $bodyParams
                ];
            }
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template
        ];
        
        logger('[TEMPLATE] Sending template: ' . json_encode($payload));

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
            $errorMessage = $e->getMessage();
            $responseBody = null;
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($responseBody, true);
                
                // Extract more detailed error message
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                }
                
                // Check for specific error codes
                if (isset($errorData['error']['code'])) {
                    $errorCode = $errorData['error']['code'];
                    
                    // Error 100 = Invalid parameter
                    if ($errorCode == 100) {
                        $errorMessage = 'Invalid template parameters. Please check: ' . 
                                       '1) Template name matches exactly (case-sensitive), ' .
                                       '2) Language code is valid (e.g., "en", "en_US"), ' .
                                       '3) Template is approved and active in WhatsApp Business Manager. ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Invalid parameter');
                    }
                }
            }
            
            logger('WhatsApp Template API Error: ' . $errorMessage, 'error');
            logger('Template Payload: ' . json_encode($payload), 'error');
            logger('Response: ' . ($responseBody ?? 'No response'), 'error');
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'response' => $responseBody,
                'payload' => $payload
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
        $mediaFilename = null;
        $mediaSize = null;
        
        logger("[SAVE] Message type: {$messageType}");

        switch ($messageType) {
            case 'text':
                $messageBody = $messageData['text']['body'] ?? '';
                break;

            case 'image':
                $mediaId = $messageData['image']['id'] ?? '';
                $mediaMimeType = $messageData['image']['mime_type'] ?? 'image/jpeg';
                $mediaCaption = $messageData['image']['caption'] ?? '';
                
                // Fetch detailed media info from WhatsApp API
                $mediaDetails = $this->fetchMediaDetails($mediaId);
                $mediaUrl = $mediaDetails['media_url'] ?? null;
                $mediaFilename = $mediaDetails['media_filename'] ?? null;
                $mediaSize = $mediaDetails['media_size'] ?? null;
                $mediaMimeType = $mediaDetails['mime_type'] ?? $mediaMimeType;
                break;

            case 'audio':
                $mediaId = $messageData['audio']['id'] ?? '';
                $mediaMimeType = $messageData['audio']['mime_type'] ?? 'audio/mpeg';
                
                $mediaDetails = $this->fetchMediaDetails($mediaId);
                $mediaUrl = $mediaDetails['media_url'] ?? null;
                $mediaFilename = $mediaDetails['media_filename'] ?? null;
                $mediaSize = $mediaDetails['media_size'] ?? null;
                $mediaMimeType = $mediaDetails['mime_type'] ?? $mediaMimeType;
                break;

            case 'video':
                $mediaId = $messageData['video']['id'] ?? '';
                $mediaMimeType = $messageData['video']['mime_type'] ?? 'video/mp4';
                $mediaCaption = $messageData['video']['caption'] ?? '';
                
                $mediaDetails = $this->fetchMediaDetails($mediaId);
                $mediaUrl = $mediaDetails['media_url'] ?? null;
                $mediaFilename = $mediaDetails['media_filename'] ?? null;
                $mediaSize = $mediaDetails['media_size'] ?? null;
                $mediaMimeType = $mediaDetails['mime_type'] ?? $mediaMimeType;
                break;

            case 'document':
                $mediaId = $messageData['document']['id'] ?? '';
                $mediaMimeType = $messageData['document']['mime_type'] ?? 'application/octet-stream';
                $mediaCaption = $messageData['document']['filename'] ?? '';
                
                $mediaDetails = $this->fetchMediaDetails($mediaId);
                $mediaUrl = $mediaDetails['media_url'] ?? null;
                $mediaFilename = $mediaDetails['media_filename'] ?? null;
                $mediaSize = $mediaDetails['media_size'] ?? null;
                $mediaMimeType = $mediaDetails['mime_type'] ?? $mediaMimeType;
                $mediaCaption = $mediaCaption ?: 'document_' . time();
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
                'media_filename' => $mediaFilename,
                'media_size' => $mediaSize,
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
            
            // Apply auto-tagging rules
            $this->applyAutoTagging($messageBody, $contact);
        }
        
        // Trigger webhooks
        $this->triggerWebhooks('message.received', [
            'message_id' => $messageId,
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $from
            ],
            'message' => [
                'type' => $messageType,
                'body' => $messageBody,
                'timestamp' => $timestamp
            ]
        ]);
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
    
    /**
     * Check incoming message for quick reply shortcuts and auto-respond
     */
    private function checkAndSendQuickReply($messageBody, $contact, $phoneNumber)
    {
        try {
            logger("[QUICK_REPLY] Checking message: " . substr($messageBody, 0, 50));
            
            // Trim and normalize the message
            $messageBody = trim($messageBody);
            $searchText = strtolower($messageBody);
            
            // Check for IP address command (dcmb ip <IP>)
            if (preg_match('/^dcmb\s+ip\s+([0-9a-fA-F:\.]+)$/i', $messageBody, $matches)) {
                $ipAddress = $matches[1];
                logger("[IP_COMMAND] Detected IP command: {$ipAddress}");
                
                try {
                    // Call the analytics API
                    $apiUrl = "https://analytics.dealcart.io/add-ip/{$ipAddress}";
                    logger("[IP_COMMAND] Calling API: {$apiUrl}");
                    
                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $apiResponse = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    logger("[IP_COMMAND] API Response Code: {$httpCode}");
                    logger("[IP_COMMAND] API Response: " . substr($apiResponse, 0, 200));
                    
                    // Save IP command to database
                    \App\Models\IpCommand::create([
                        'ip_address' => $ipAddress,
                        'contact_name' => $contact->name,
                        'phone_number' => $phoneNumber,
                        'api_response' => $apiResponse,
                        'http_code' => $httpCode,
                        'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed'
                    ]);
                    
                    // Send the API response back to user
                    $responseMessage = "IP Command Result:\n\n{$apiResponse}";
                    $response = $this->sendTextMessage($phoneNumber, $responseMessage);
                    
                    if ($response['success']) {
                        // Save the outgoing message to database
                        Message::create([
                            'contact_id' => $contact->id,
                            'phone_number' => $phoneNumber,
                            'message_id' => $response['message_id'],
                            'message_type' => 'text',
                            'direction' => 'outgoing',
                            'message_body' => $responseMessage,
                            'timestamp' => now(),
                            'status' => 'sent',
                            'is_read' => true
                        ]);
                        logger("[IP_COMMAND] ✅ Response sent successfully");
                    }
                    
                    return; // Exit early after handling IP command
                } catch (\Exception $e) {
                    logger("[IP_COMMAND] ❌ Error: " . $e->getMessage(), 'error');
                    
                    // Save failed command
                    \App\Models\IpCommand::create([
                        'ip_address' => $ipAddress,
                        'contact_name' => $contact->name,
                        'phone_number' => $phoneNumber,
                        'api_response' => $e->getMessage(),
                        'http_code' => 0,
                        'status' => 'failed'
                    ]);
                    
                    $this->sendTextMessage($phoneNumber, "Error processing IP command: " . $e->getMessage());
                    return;
                }
            }
            
            // Fuzzy matching: Find quick replies that match the message
            logger("[QUICK_REPLY] Starting fuzzy search for message: {$searchText}");
            
            // Get all active quick replies
            $allQuickReplies = \App\Models\QuickReply::where('is_active', true)->get();
            $quickReply = null;
            
            // Try exact match first (with or without /)
            foreach ($allQuickReplies as $qr) {
                $qrShortcut = strtolower(trim($qr->shortcut));
                $qrShortcutNoSlash = ltrim($qrShortcut, '/');
                
                if ($searchText === $qrShortcut || $searchText === $qrShortcutNoSlash) {
                    $quickReply = $qr;
                    logger("[QUICK_REPLY] ✅ Exact match found: {$qr->shortcut}");
                    break;
                }
            }
            
            // If no exact match, try fuzzy matching (keyword appears anywhere in message)
            if (!$quickReply) {
                foreach ($allQuickReplies as $qr) {
                    $qrShortcut = strtolower(ltrim(trim($qr->shortcut), '/'));
                    
                    // Check if the shortcut keyword appears in the message
                    if (strpos($searchText, $qrShortcut) !== false) {
                        $quickReply = $qr;
                        logger("[QUICK_REPLY] ✅ Fuzzy match found: {$qr->shortcut} in message");
                        break;
                    }
                }
            }
            
            logger("[QUICK_REPLY] Final result: " . ($quickReply ? "FOUND - {$quickReply->shortcut}" : "NOT FOUND"));
            
            if ($quickReply) {
                logger("[QUICK_REPLY] Match found: " . $quickReply->title);
                
                // Send the quick reply message
                $response = $this->sendTextMessage($phoneNumber, $quickReply->message);
                
                logger("[QUICK_REPLY] Send response: " . json_encode($response));
                
                if ($response['success']) {
                    // Increment usage count
                    $quickReply->increment('usage_count');
                    logger("[QUICK_REPLY] ✅ Sent successfully, usage count: " . $quickReply->usage_count);
                    
                    // Save the outgoing message to database
                    Message::create([
                        'contact_id' => $contact->id,
                        'phone_number' => $phoneNumber,
                        'message_id' => $response['message_id'],
                        'message_type' => 'text',
                        'direction' => 'outgoing',
                        'message_body' => $quickReply->message,
                        'timestamp' => now(),
                        'status' => 'sent',
                        'is_read' => true
                    ]);
                } else {
                    logger("[QUICK_REPLY] ❌ Failed to send: " . ($response['error'] ?? 'Unknown error'), 'error');
                }
            } else {
                logger("[QUICK_REPLY] No active quick reply found for: {$shortcut}");
            }
        } catch (\Exception $e) {
            logger("[QUICK_REPLY ERROR] " . $e->getMessage(), 'error');
            logger("[QUICK_REPLY ERROR] Stack: " . $e->getTraceAsString(), 'error');
        }
    }
    
    /**
     * Apply auto-tagging rules to contact based on message content
     */
    private function applyAutoTagging($messageBody, $contact)
    {
        try {
            logger("[AUTO_TAG] Checking rules for message: " . substr($messageBody, 0, 50));
            
            $rules = \App\Models\AutoTagRule::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get();
            
            foreach ($rules as $rule) {
                if ($rule->matches($messageBody)) {
                    // Check if contact already has this tag
                    $hasTag = $contact->contactTags()->where('tag_id', $rule->tag_id)->exists();
                    
                    if (!$hasTag) {
                        $contact->contactTags()->attach($rule->tag_id);
                        $rule->incrementUsage();
                        logger("[AUTO_TAG] ✅ Applied tag {$rule->tag_id} to contact {$contact->id}");
                    }
                }
            }
        } catch (\Exception $e) {
            logger("[AUTO_TAG ERROR] " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Trigger webhooks for an event
     */
    private function triggerWebhooks($event, $payload)
    {
        try {
            $webhooks = \App\Models\Webhook::where('is_active', true)->get();
            
            foreach ($webhooks as $webhook) {
                if ($webhook->listensTo($event)) {
                    $webhook->trigger($event, $payload);
                }
            }
        } catch (\Exception $e) {
            logger("[WEBHOOK ERROR] " . $e->getMessage(), 'error');
        }
    }

    /**
     * Fetch media details from WhatsApp API and download to local storage
     */
    private function fetchMediaDetails($mediaId)
    {
        // WhatsApp media details are fetched via Graph API (facebook domain, not instagram)
        // Endpoint: GET https://graph.facebook.com/{version}/{media-id}?fields=url,file_size,mime_type,sha256
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$mediaId}/";

        try {
            logger("[MEDIA] Fetching details for media ID: {$mediaId} via {$url}");

            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json'
                ],
                'query' => [
                    'fields' => 'url,file_size,mime_type,sha256,id'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            logger("[MEDIA] Response: " . json_encode($data));

            $mediaUrl = $data['url'] ?? null;
            $mediaSize = $data['file_size'] ?? null;
            $mimeType = $data['mime_type'] ?? null;
            $extension = $mimeType ? $this->inferExtension($mimeType) : '';
            $filename = $mediaId . $extension;

            // Download media to local storage (URLs expire, so cache locally)
            $localFilename = null;
            if ($mediaUrl) {
                try {
                    $localFilename = $this->downloadMedia($mediaUrl, $filename, $mimeType);
                    logger("[MEDIA] Downloaded to local file: {$localFilename}");
                } catch (\Exception $e) {
                    logger("[MEDIA] Failed to download media: " . $e->getMessage(), 'warning');
                    // Fall back to media_url if download fails
                    $localFilename = null;
                }
            }

            return [
                'media_url'   => $localFilename ? null : $mediaUrl,  // Use local file if available
                'media_filename' => $localFilename,
                'media_size'  => $mediaSize,
                'filename'    => $filename,
                'mime_type'   => $mimeType,
            ];
        } catch (\Exception $e) {
            logger("[MEDIA ERROR] Failed to fetch media details: " . $e->getMessage(), 'error');
            return [
                'media_url'  => null,
                'media_filename' => null,
                'media_size' => null,
                'filename'   => null,
                'mime_type'  => null,
            ];
        }
    }

    /**
     * Download media from WhatsApp URL and save locally
     */
    private function downloadMedia($mediaUrl, $filename, $mimeType)
    {
        $uploadDir = __DIR__ . '/../../uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Use timestamp + random to avoid collisions
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        $localPath = $uploadDir . '/' . time() . '_' . $safeName;

        logger("[MEDIA DOWNLOAD] Downloading from {$mediaUrl} to {$localPath}");

        try {
            $response = $this->client->request('GET', $mediaUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
                'timeout' => 30,
                'verify' => true
            ]);

            $content = $response->getBody();
            file_put_contents($localPath, $content);

            // Return just the filename for relative URL construction
            return basename($localPath);
        } catch (\Exception $e) {
            logger("[MEDIA DOWNLOAD ERROR] " . $e->getMessage(), 'error');
            throw $e;
        }
    }

    /**
     * Infer file extension from mime type for fallback filenames
     */
    private function inferExtension($mime)
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'video/mp4' => '.mp4',
            'audio/mpeg' => '.mp3',
            'application/pdf' => '.pdf'
        ];
        return $map[$mime] ?? '';
    }
}
