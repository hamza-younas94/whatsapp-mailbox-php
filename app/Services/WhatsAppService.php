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
     * Get available message templates
     */
    public function getTemplates()
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/message_templates";
        
        try {
            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            if (isset($result['data'])) {
                return [
                    'success' => true,
                    'templates' => $result['data'],
                    'data' => $result
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Failed to fetch templates',
                'data' => $result
            ];
            
        } catch (RequestException $e) {
            $errorMessage = $e->getMessage();
            $responseBody = null;
            
            if ($e->hasResponse()) {
                $responseBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($responseBody, true);
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                }
            }
            
            logger('WhatsApp Get Templates API Error: ' . $errorMessage, 'error');
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'response' => $responseBody
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
        
        // Normalize language code - WhatsApp API expects format like "en_US" (lowercase language, uppercase country)
        // Convert input to proper format: "en_us" or "en_US" -> "en_US"
        $languageCode = trim($languageCode);
        
        if (empty($languageCode)) {
            $languageCode = 'en';
        } else {
            // Split by underscore if present
            if (strpos($languageCode, '_') !== false) {
                $parts = explode('_', $languageCode);
                $lang = strtolower($parts[0]); // Language part lowercase
                $country = isset($parts[1]) ? strtoupper($parts[1]) : ''; // Country part uppercase
                $languageCode = $lang . ($country ? '_' . $country : '');
            } else {
                // Single part like "en" - keep lowercase
                $languageCode = strtolower($languageCode);
            }
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
                        // Try to get available templates to help diagnose
                        $templatesResult = $this->getTemplates();
                        $availableTemplates = [];
                        
                        if ($templatesResult['success'] && isset($templatesResult['templates'])) {
                            foreach ($templatesResult['templates'] as $template) {
                                $availableTemplates[] = [
                                    'name' => $template['name'] ?? 'N/A',
                                    'language' => $template['language'] ?? 'N/A',
                                    'status' => $template['status'] ?? 'N/A'
                                ];
                            }
                        }
                        
                        $errorMessage = 'Invalid template parameters. Please check: ' . 
                                       '1) Template name matches exactly (case-sensitive), ' .
                                       '2) Language code is valid (e.g., "en", "en_US"), ' .
                                       '3) Template is approved and active in WhatsApp Business Manager. ' .
                                       '4) Template exists for this phone number ID. ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Invalid parameter');
                        
                        if (!empty($availableTemplates)) {
                            $errorMessage .= '. Available templates: ' . json_encode($availableTemplates);
                        }
                    }
                    
                    // Error 132000 = Parameter count mismatch
                    if ($errorCode == 132000) {
                        $errorDetails = $errorData['error']['error_data']['details'] ?? '';
                        $errorMessage = 'Template parameter mismatch. ' . 
                                       'The number of parameters you provided does not match what the template expects. ' .
                                       'Please check the template in WhatsApp Business Manager to see how many parameters it requires. ' .
                                       'If the template has no placeholders ({{1}}, {{2}}, etc.) in the body, do not add any parameters. ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Parameter mismatch');
                        
                        if ($errorDetails) {
                            $errorMessage .= ' (' . $errorDetails . ')';
                        }
                    }
                    
                    // Error 132001 = Template doesn't exist in language
                    if ($errorCode == 132001) {
                        $errorDetails = $errorData['error']['error_data']['details'] ?? '';
                        $errorMessage = 'Template not found in this language. ' .
                                       'The template "' . $templateName . '" does not exist in language "' . $languageCode . '". ' .
                                       'Please check WhatsApp Business Manager to see which languages are available for this template. ' .
                                       'You may need to use a different language code (e.g., "en" instead of "en_US"). ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Template not found');
                        
                        if ($errorDetails) {
                            $errorMessage .= ' (' . $errorDetails . ')';
                        }
                    }
                    
                    // Error 131042 = Business eligibility payment issue
                    if ($errorCode == 131042) {
                        $errorMessage = 'Business eligibility payment issue. ' .
                                       'Your WhatsApp Business account has a payment or billing issue. ' .
                                       'Please check your WhatsApp Business Manager billing settings and ensure your payment method is valid. ' .
                                       'You may need to add a payment method or resolve billing issues before sending messages. ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Payment issue');
                    }
                    
                    // Error 132000 = Parameter count mismatch
                    if ($errorCode == 132000) {
                        $errorDetails = $errorData['error']['error_data']['details'] ?? '';
                        $errorMessage = 'Template parameter mismatch. ' . 
                                       'The number of parameters you provided does not match what the template expects. ' .
                                       'Please check the template in WhatsApp Business Manager to see how many parameters it requires. ' .
                                       'If the template has no placeholders ({{1}}, {{2}}, etc.), do not add any parameters. ' .
                                       'Error details: ' . ($errorData['error']['message'] ?? 'Parameter mismatch');
                        
                        if ($errorDetails) {
                            $errorMessage .= ' (' . $errorDetails . ')';
                        }
                    }
                }
            }
            
            logger('WhatsApp Template API Error: ' . $errorMessage, 'error');
            logger('Template Payload: ' . json_encode($payload), 'error');
            logger('Response: ' . ($responseBody ?? 'No response'), 'error');
            logger('Phone Number ID: ' . $this->phoneNumberId, 'error');
            logger('API Version: ' . $this->apiVersion, 'error');
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'response' => $responseBody,
                'payload' => $payload,
                'phone_number_id' => $this->phoneNumberId,
                'api_version' => $this->apiVersion
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
            // Detect if message is from a group (groups usually have @g.us suffix or context.group_id)
            $isGroup = false;
            if (isset($messageData['context']['from'])) {
                $isGroup = strpos($messageData['context']['from'], '@g.us') !== false;
            } elseif (isset($messageData['context']['group_id'])) {
                $isGroup = true;
            } elseif (isset($messageData['from']) && strpos($messageData['from'], '@g.us') !== false) {
                $isGroup = true;
            }
            
            $this->checkAndSendQuickReply($messageBody, $contact, $from, $isGroup);
            
            // Apply auto-tagging rules
            $this->applyAutoTagging($messageBody, $contact);
            
            // Check and trigger workflows
            $this->checkAndTriggerWorkflows($contact, [
                'trigger_type' => 'new_message',
                'message' => $messageBody,
                'keyword' => $messageBody
            ]);
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
        $errors = $status['errors'] ?? [];

        $updateData = ['status' => $newStatus];
        
        // Store error information if message failed
        if ($newStatus === 'failed' && !empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorCode = $error['code'] ?? '';
                $errorMessage = $error['message'] ?? '';
                $errorTitle = $error['title'] ?? '';
                
                $errorMessages[] = [
                    'code' => $errorCode,
                    'title' => $errorTitle,
                    'message' => $errorMessage
                ];
                
                // Log specific error codes
                if ($errorCode == 131042) {
                    logger("[STATUS] Message failed due to payment issue (131042): {$messageId}", 'error');
                }
            }
            
            // Store error info in a JSON field if available, or in message_body as note
            if (!empty($errorMessages)) {
                $updateData['error_info'] = json_encode($errorMessages);
            }
        }

        $updated = Message::where('message_id', $messageId)
            ->update($updateData);
            
        if ($updated) {
            logger("[STATUS] Updated message {$messageId} to status: {$newStatus}");
        } else {
            logger("[STATUS] Message {$messageId} not found in database", 'warning');
        }
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
     * Enhanced with better matching, rate limiting, and error handling
     */
    private function checkAndSendQuickReply($messageBody, $contact, $phoneNumber, $isGroup = false)
    {
        try {
            logger("[QUICK_REPLY] Checking message: " . substr($messageBody, 0, 50) . ($isGroup ? " [GROUP]" : ""));
            
            // Trim and normalize the message
            $messageBody = trim($messageBody);
            if (empty($messageBody)) {
                logger("[QUICK_REPLY] Empty message, skipping");
                return;
            }
            
            $searchText = strtolower($messageBody);
            $originalText = $messageBody;
            
            logger("[QUICK_REPLY] Passed empty check, searchText: '{$searchText}'");
            
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
            
            logger("[QUICK_REPLY] Passed IP command check");
            
            // Rate limiting: Check if we sent a reply to this contact recently (within last 30 seconds)
            try {
                $cutoffTime = date('Y-m-d H:i:s', time() - 30);
                logger("[QUICK_REPLY] Checking rate limit - Contact ID: {$contact->id}, Cutoff: {$cutoffTime}");
                
                $recentReplyQuery = Message::where('contact_id', $contact->id)
                    ->where('direction', 'outgoing')
                    ->where('message_body', 'like', '%[AUTO-REPLY]%')
                    ->where('timestamp', '>', $cutoffTime);
                
                $recentReplyCount = $recentReplyQuery->count();
                logger("[QUICK_REPLY] Found {$recentReplyCount} recent auto-replies in last 30 seconds");
                
                if ($recentReplyCount > 0) {
                    $recentMessages = $recentReplyQuery->limit(3)->get(['message_body', 'timestamp']);
                    foreach ($recentMessages as $msg) {
                        logger("[QUICK_REPLY] Recent: " . substr($msg->message_body, 0, 50) . " at " . $msg->timestamp);
                    }
                    logger("[QUICK_REPLY] Rate limit: Recent auto-reply sent, skipping to prevent spam");
                    return;
                }
            } catch (\Exception $e) {
                logger("[QUICK_REPLY] Rate limit check error: " . $e->getMessage(), 'error');
                // Continue anyway if rate limit check fails
            }
            
            logger("[QUICK_REPLY] Passed rate limit check");
            
            // Get all active quick replies, ordered by priority (desc) then usage count (desc)
            $allQuickReplies = \App\Models\QuickReply::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderBy('usage_count', 'desc')
                ->get();
            
            logger("[QUICK_REPLY] Found " . $allQuickReplies->count() . " active quick replies");
            
            if ($allQuickReplies->isEmpty()) {
                logger("[QUICK_REPLY] No active quick replies found");
                return;
            }
            
            logger("[QUICK_REPLY] Search text: '{$searchText}'");
            
            // Find matching reply with all filters
            $matchedReply = null;
            
            foreach ($allQuickReplies as $qr) {
                // Check contact filtering (blacklist/whitelist)
                if (!$this->passesContactFilter($qr, $contact)) {
                    logger("[QUICK_REPLY] Reply '{$qr->title}' filtered by contact filter");
                    continue;
                }
                
                // Check conditions
                if (!$qr->conditionsMet($contact)) {
                    logger("[QUICK_REPLY] Reply '{$qr->title}' filtered by conditions");
                    continue;
                }
                
                // Check business hours
                if (!$qr->isWithinBusinessHours()) {
                    if ($qr->outside_hours_message) {
                        // Send outside hours message instead
                        $matchedReply = $qr;
                        $matchedReply->message = $qr->outside_hours_message;
                        logger("[QUICK_REPLY] Outside business hours, using outside hours message");
                    } else {
                        logger("[QUICK_REPLY] Reply '{$qr->title}' filtered by business hours");
                        continue;
                    }
                    break;
                }
                
                // Check if matches using model's matches() method
                if ($qr->matches($searchText)) {
                    $matchedReply = $qr;
                    logger("[QUICK_REPLY] ✅ Match found: '{$qr->title}'");
                    break;
                }
            }
            
            if ($matchedReply) {
                logger("[QUICK_REPLY] Processing match: '{$matchedReply->title}'");
                
                // Apply delay if configured
                if ($matchedReply->delay_seconds > 0) {
                    logger("[QUICK_REPLY] Waiting {$matchedReply->delay_seconds} seconds before sending");
                    sleep($matchedReply->delay_seconds);
                }
                
                // Check if this is a sequence or single message
                if (!empty($matchedReply->sequence_messages) && is_array($matchedReply->sequence_messages)) {
                    // Send sequence of messages
                    $this->sendQuickReplySequence($matchedReply, $contact, $phoneNumber, $originalText);
                } else {
                    // Send single message (with optional media)
                    $this->sendQuickReplyMessage($matchedReply, $contact, $phoneNumber, $originalText);
                }
            } else {
                logger("[QUICK_REPLY] No match found for message: " . substr($searchText, 0, 50));
            }
        } catch (\Exception $e) {
            logger("[QUICK_REPLY ERROR] " . $e->getMessage(), 'error');
            logger("[QUICK_REPLY ERROR] Stack: " . $e->getTraceAsString(), 'error');
        }
    }
    
    /**
     * Check if contact passes filter (blacklist/whitelist)
     */
    private function passesContactFilter($quickReply, $contact)
    {
        // Check excluded contacts (blacklist)
        if (!empty($quickReply->excluded_contact_ids) && is_array($quickReply->excluded_contact_ids)) {
            if (in_array($contact->id, $quickReply->excluded_contact_ids)) {
                return false;
            }
        }
        
        // Check included contacts (whitelist) - if set, only these contacts are allowed
        if (!empty($quickReply->included_contact_ids) && is_array($quickReply->included_contact_ids)) {
            if (!in_array($contact->id, $quickReply->included_contact_ids)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send quick reply message (single or with media)
     */
    private function sendQuickReplyMessage($quickReply, $contact, $phoneNumber, $originalMessage)
    {
        // Process message with advanced variables
        $replyMessage = $this->processQuickReplyMessage($quickReply->message, $contact, $originalMessage);
        
        $response = null;
        
        // Check if has media
        if (!empty($quickReply->media_url) || !empty($quickReply->media_filename)) {
            logger("[QUICK_REPLY] Sending message with media");
            $mediaUrl = $quickReply->media_url;
            
            // If media_filename is set, construct local path
            if (empty($mediaUrl) && !empty($quickReply->media_filename)) {
                $mediaUrl = __DIR__ . '/../../uploads/' . $quickReply->media_filename;
            }
            
            $mediaType = $quickReply->media_type ?? 'image';
            
            // Send media message
            $response = $this->sendMediaMessage(
                $phoneNumber,
                $mediaUrl,
                $mediaType,
                $replyMessage // Caption
            );
        } else {
            // Send text message
            $response = $this->sendQuickReplyWithRetry($phoneNumber, $replyMessage, 2);
        }
        
        if ($response && $response['success']) {
            // Update analytics
            $quickReply->incrementUsage();
            $quickReply->incrementSuccess();
            
            logger("[QUICK_REPLY] ✅ Sent successfully");
            
            // Save the outgoing message to database with auto-reply marker
            Message::create([
                'contact_id' => $contact->id,
                'phone_number' => $phoneNumber,
                'message_id' => $response['message_id'] ?? null,
                'message_type' => !empty($quickReply->media_url) ? $quickReply->media_type : 'text',
                'direction' => 'outgoing',
                'message_body' => '[AUTO-REPLY] ' . $replyMessage,
                'media_url' => $quickReply->media_url ?? null,
                'media_filename' => $quickReply->media_filename ?? null,
                'timestamp' => now(),
                'status' => 'sent',
                'is_read' => true
            ]);
        } else {
            // Track failure
            $quickReply->incrementFailure();
            logger("[QUICK_REPLY] ❌ Failed to send: " . ($response['error'] ?? 'Unknown error'), 'error');
        }
    }
    
    /**
     * Send quick reply sequence (multiple messages)
     */
    private function sendQuickReplySequence($quickReply, $contact, $phoneNumber, $originalMessage)
    {
        $sequenceMessages = $quickReply->sequence_messages;
        $delaySeconds = $quickReply->sequence_delay_seconds ?? 2;
        
        logger("[QUICK_REPLY] Sending sequence of " . count($sequenceMessages) . " messages");
        
        $allSuccess = true;
        
        foreach ($sequenceMessages as $index => $seqMessage) {
            // Process message with variables
            $message = is_array($seqMessage) ? ($seqMessage['message'] ?? '') : $seqMessage;
            $processedMessage = $this->processQuickReplyMessage($message, $contact, $originalMessage);
            
            // Check if this message has media
            $mediaUrl = is_array($seqMessage) ? ($seqMessage['media_url'] ?? null) : null;
            $mediaType = is_array($seqMessage) ? ($seqMessage['media_type'] ?? 'image') : 'image';
            
            $response = null;
            
            if ($mediaUrl) {
                $response = $this->sendMediaMessage($phoneNumber, $mediaUrl, $mediaType, $processedMessage);
            } else {
                $response = $this->sendQuickReplyWithRetry($phoneNumber, $processedMessage, 2);
            }
            
            if ($response && $response['success']) {
                // Save message to database
                Message::create([
                    'contact_id' => $contact->id,
                    'phone_number' => $phoneNumber,
                    'message_id' => $response['message_id'] ?? null,
                    'message_type' => $mediaUrl ? $mediaType : 'text',
                    'direction' => 'outgoing',
                    'message_body' => '[AUTO-REPLY SEQUENCE ' . ($index + 1) . '/' . count($sequenceMessages) . '] ' . $processedMessage,
                    'media_url' => $mediaUrl ?? null,
                    'timestamp' => now(),
                    'status' => 'sent',
                    'is_read' => true
                ]);
                
                // Wait before next message (except for last one)
                if ($index < count($sequenceMessages) - 1 && $delaySeconds > 0) {
                    sleep($delaySeconds);
                }
            } else {
                $allSuccess = false;
                logger("[QUICK_REPLY] ❌ Failed to send sequence message " . ($index + 1), 'error');
                break; // Stop sequence on failure
            }
        }
        
        // Update analytics
        $quickReply->incrementUsage();
        if ($allSuccess) {
            $quickReply->incrementSuccess();
        } else {
            $quickReply->incrementFailure();
        }
    }
    
    /**
     * Process quick reply message with advanced variables
     */
    private function processQuickReplyMessage($message, $contact, $originalMessage = '')
    {
        // Get contact's company name, stage, etc.
        $companyName = $contact->company_name ?? '';
        $stage = $contact->stage ?? '';
        $email = $contact->email ?? '';
        $city = $contact->city ?? '';
        $country = $contact->country ?? '';
        
        // Get message count
        $messageCount = Message::where('contact_id', $contact->id)->count();
        
        // Get last message date/time
        $lastMessage = Message::where('contact_id', $contact->id)
            ->orderBy('timestamp', 'desc')
            ->first();
        $lastMessageDate = $lastMessage ? date('Y-m-d', strtotime($lastMessage->timestamp)) : '';
        $lastMessageTime = $lastMessage ? date('H:i:s', strtotime($lastMessage->timestamp)) : '';
        
        // Current date/time
        $now = new \DateTime();
        $currentDate = $now->format('Y-m-d');
        $currentTime = $now->format('H:i:s');
        $currentDateTime = $now->format('Y-m-d H:i:s');
        
        // Replace variables in message
        $replacements = [
            // Basic contact info
            '{{name}}' => $contact->name ?? '',
            '{{contact_name}}' => $contact->name ?? '',
            '{{phone}}' => $contact->phone_number ?? '',
            '{{phone_number}}' => $contact->phone_number ?? '',
            '{{message}}' => $originalMessage,
            '{{user_message}}' => $originalMessage,
            
            // Advanced contact info
            '{{company}}' => $companyName,
            '{{company_name}}' => $companyName,
            '{{stage}}' => $stage,
            '{{email}}' => $email,
            '{{city}}' => $city,
            '{{country}}' => $country,
            
            // Date/time variables
            '{{date}}' => $currentDate,
            '{{time}}' => $currentTime,
            '{{datetime}}' => $currentDateTime,
            '{{current_date}}' => $currentDate,
            '{{current_time}}' => $currentTime,
            
            // Last message date/time
            '{{last_message_date}}' => $lastMessageDate,
            '{{last_message_time}}' => $lastMessageTime,
            
            // Statistics
            '{{message_count}}' => $messageCount,
            '{{total_messages}}' => $messageCount,
        ];
        
        $processedMessage = $message;
        foreach ($replacements as $placeholder => $value) {
            $processedMessage = str_replace($placeholder, (string)$value, $processedMessage);
        }
        
        return $processedMessage;
    }
    
    /**
     * Send quick reply with retry logic
     */
    private function sendQuickReplyWithRetry($phoneNumber, $message, $maxRetries = 2)
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            logger("[QUICK_REPLY] Sending attempt {$attempt}/{$maxRetries}");
            
            $response = $this->sendTextMessage($phoneNumber, $message);
            
            if ($response['success']) {
                return $response;
            }
            
            $lastError = $response['error'] ?? 'Unknown error';
            logger("[QUICK_REPLY] Attempt {$attempt} failed: {$lastError}");
            
            // Wait before retry (exponential backoff)
            if ($attempt < $maxRetries) {
                sleep(pow(2, $attempt - 1)); // 1s, 2s, 4s...
            }
        }
        
        return [
            'success' => false,
            'error' => "Failed after {$maxRetries} attempts: {$lastError}"
        ];
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
    
    /**
     * Execute workflow for a contact
     */
    public function executeWorkflow($workflow, $contact, $context = [])
    {
        try {
            if (!$workflow->is_active) {
                return ['success' => false, 'error' => 'Workflow is not active'];
            }
            
            // Check if trigger conditions are met
            if (!$this->checkWorkflowTrigger($workflow, $contact, $context)) {
                return ['success' => false, 'error' => 'Trigger conditions not met'];
            }
            
            $actionsPerformed = [];
            $errors = [];
            
            // Execute each action
            foreach ($workflow->actions as $action) {
                try {
                    $result = $this->executeWorkflowAction($action, $contact);
                    $actionsPerformed[] = [
                        'action' => $action,
                        'result' => $result
                    ];
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                    logger("[WORKFLOW] Action failed: " . $e->getMessage(), 'error');
                }
            }
            
            // Log execution
            \App\Models\WorkflowExecution::create([
                'workflow_id' => $workflow->id,
                'contact_id' => $contact->id,
                'status' => empty($errors) ? 'success' : 'failed',
                'actions_performed' => $actionsPerformed,
                'error_message' => !empty($errors) ? implode(', ', $errors) : null,
                'executed_at' => now()
            ]);
            
            // Update workflow stats
            $workflow->increment('execution_count');
            $workflow->update(['last_executed_at' => now()]);
            
            return [
                'success' => empty($errors),
                'actions_performed' => $actionsPerformed,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            logger("[WORKFLOW ERROR] " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check if workflow trigger conditions are met
     */
    private function checkWorkflowTrigger($workflow, $contact, $context)
    {
        $conditions = $workflow->trigger_conditions ?? [];
        $triggerType = $workflow->trigger_type;
        
        switch ($triggerType) {
            case 'new_message':
                $keyword = $conditions['keyword'] ?? null;
                if ($keyword && isset($context['message'])) {
                    return stripos($context['message'], $keyword) !== false;
                }
                return true; // No keyword = always trigger
                
            case 'stage_change':
                $fromStage = $conditions['from_stage'] ?? null;
                $toStage = $conditions['to_stage'] ?? null;
                if ($fromStage && isset($context['from_stage'])) {
                    if ($context['from_stage'] !== $fromStage) return false;
                }
                if ($toStage && isset($context['to_stage'])) {
                    if ($context['to_stage'] !== $toStage) return false;
                }
                return isset($context['to_stage']); // At least one stage should be set
                
            case 'tag_added':
            case 'tag_removed':
                $tagId = $conditions['tag_id'] ?? null;
                if ($tagId && isset($context['tag_id'])) {
                    return $context['tag_id'] == $tagId;
                }
                return isset($context['tag_id']);
                
            case 'lead_score_change':
                $score = $conditions['score'] ?? null;
                $direction = $conditions['direction'] ?? 'above';
                if ($score && isset($context['lead_score'])) {
                    if ($direction === 'above') {
                        return $context['lead_score'] >= $score;
                    } else {
                        return $context['lead_score'] <= $score;
                    }
                }
                return isset($context['lead_score']);
                
            case 'time_based':
                // Time-based workflows are handled by cron
                return isset($context['test_mode']) || false;
                
            default:
                return true; // Unknown trigger = always execute in test mode
        }
    }
    
    /**
     * Execute a single workflow action
     */
    private function executeWorkflowAction($action, $contact)
    {
        $actionType = $action['type'] ?? '';
        
        switch ($actionType) {
            case 'send_message':
                $message = $this->processQuickReplyMessage(
                    $action['message'] ?? '',
                    $contact
                );
                return $this->sendTextMessage($contact->phone_number, $message);
                
            case 'send_template':
                $templateName = $action['template'] ?? '';
                $params = $action['params'] ?? [];
                $languageCode = $action['language_code'] ?? 'en';
                return $this->sendTemplateMessage(
                    $contact->phone_number,
                    $templateName,
                    $languageCode,
                    $params
                );
                
            case 'add_tag':
                $tagId = $action['tag_id'] ?? null;
                if ($tagId) {
                    $contact->tags()->syncWithoutDetaching([$tagId]);
                    return ['success' => true, 'message' => 'Tag added'];
                }
                return ['success' => false, 'error' => 'Tag ID missing'];
                
            case 'remove_tag':
                $tagId = $action['tag_id'] ?? null;
                if ($tagId) {
                    $contact->tags()->detach($tagId);
                    return ['success' => true, 'message' => 'Tag removed'];
                }
                return ['success' => false, 'error' => 'Tag ID missing'];
                
            case 'change_stage':
                $stage = $action['stage'] ?? null;
                if ($stage) {
                    $contact->update(['stage' => $stage]);
                    return ['success' => true, 'message' => 'Stage changed to ' . $stage];
                }
                return ['success' => false, 'error' => 'Stage missing'];
                
            case 'create_note':
                $noteText = $action['note'] ?? '';
                $noteType = $action['note_type'] ?? 'general';
                if ($noteText) {
                    \App\Models\Note::create([
                        'contact_id' => $contact->id,
                        'note' => $noteText,
                        'note_type' => $noteType,
                        'created_by' => $_SESSION['user_id'] ?? 1
                    ]);
                    return ['success' => true, 'message' => 'Note created'];
                }
                return ['success' => false, 'error' => 'Note text missing'];
                
            case 'assign_contact':
                $userId = $action['user_id'] ?? null;
                if ($userId) {
                    $contact->update(['assigned_to' => $userId]);
                    return ['success' => true, 'message' => 'Contact assigned'];
                }
                return ['success' => false, 'error' => 'User ID missing'];
                
            default:
                return ['success' => false, 'error' => 'Unknown action type: ' . $actionType];
        }
    }
    
    /**
     * Send next step in drip campaign for a subscriber
     */
    public function sendDripCampaignStep($subscriber, $forceNow = false)
    {
        try {
            $campaign = $subscriber->campaign;
            $contact = $subscriber->contact;
            
            if (!$campaign || !$contact) {
                return ['success' => false, 'error' => 'Campaign or contact not found'];
            }
            
            if ($subscriber->status !== 'active') {
                return ['success' => false, 'error' => 'Subscriber is not active'];
            }
            
            // Check if it's time to send
            if (!$forceNow && $subscriber->next_send_at && strtotime($subscriber->next_send_at) > time()) {
                return ['success' => false, 'error' => 'Not time to send yet. Next send: ' . $subscriber->next_send_at];
            }
            
            // Get next step
            $currentStep = $subscriber->current_step;
            $steps = $campaign->steps()->orderBy('step_number')->get();
            
            if ($currentStep >= $steps->count()) {
                // Campaign completed
                $subscriber->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                $campaign->increment('completed_count');
                return ['success' => true, 'message' => 'Campaign completed', 'completed' => true];
            }
            
            $step = $steps[$currentStep];
            
            // Send step message
            $message = $step->message_content;
            $processedMessage = $this->processQuickReplyMessage($message, $contact);
            
            $response = null;
            if ($step->message_type === 'template' && $step->template_id) {
                $template = \App\Models\MessageTemplate::find($step->template_id);
                if ($template) {
                    $params = []; // Extract params if needed
                    $response = $this->sendTemplateMessage(
                        $contact->phone_number,
                        $template->whatsapp_template_name,
                        $template->language_code,
                        $params
                    );
                }
            } else {
                $response = $this->sendTextMessage($contact->phone_number, $processedMessage);
            }
            
            if ($response && $response['success']) {
                // Update subscriber
                $nextStep = $currentStep + 1;
                $nextSendAt = null;
                
                if ($nextStep < $steps->count()) {
                    // Calculate next send time
                    $nextStepObj = $steps[$nextStep];
                    $delayMinutes = $nextStepObj->delay_minutes ?? 0;
                    $nextSendAt = now()->addMinutes($delayMinutes);
                } else {
                    // Campaign completed
                    $subscriber->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'current_step' => $nextStep
                    ]);
                    $campaign->increment('completed_count');
                }
                
                if ($nextSendAt) {
                    $subscriber->update([
                        'current_step' => $nextStep,
                        'next_send_at' => $nextSendAt
                    ]);
                }
                
                // Update step stats
                $step->increment('sent_count');
                
                // Save to message history
                try {
                    \App\Models\Message::create([
                        'contact_id' => $contact->id,
                        'phone_number' => $contact->phone_number,
                        'message_id' => $response['message_id'] ?? null,
                        'message_type' => 'text',
                        'direction' => 'outgoing',
                        'message_body' => '[DRIP: ' . $campaign->name . ' - Step ' . ($currentStep + 1) . '] ' . $processedMessage,
                        'timestamp' => now(),
                        'status' => 'sent',
                        'is_read' => true
                    ]);
                } catch (\Exception $e) {
                    logger("[DRIP] Failed to save message history: " . $e->getMessage(), 'error');
                }
                
                return [
                    'success' => true,
                    'step_name' => $step->name,
                    'message' => $processedMessage,
                    'next_send_at' => $nextSendAt,
                    'completed' => !$nextSendAt
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to send message'
                ];
            }
        } catch (\Exception $e) {
            logger("[DRIP ERROR] " . $e->getMessage(), 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check and trigger workflows for a contact
     */
    public function checkAndTriggerWorkflows($contact, $context = [])
    {
        try {
            $workflows = \App\Models\Workflow::where('is_active', true)->get();
            
            foreach ($workflows as $workflow) {
                // Skip time-based workflows (handled by cron)
                if ($workflow->trigger_type === 'time_based' && !isset($context['test_mode'])) {
                    continue;
                }
                
                try {
                    $this->executeWorkflow($workflow, $contact, $context);
                } catch (\Exception $e) {
                    logger("[WORKFLOW] Failed to execute workflow {$workflow->id}: " . $e->getMessage(), 'error');
                }
            }
        } catch (\Exception $e) {
            logger("[WORKFLOW ERROR] " . $e->getMessage(), 'error');
        }
    }
}
