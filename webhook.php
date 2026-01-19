<?php
/**
 * WhatsApp Webhook Handler - Multi-Tenant Support
 * Routes webhooks to the correct user's API credentials
 */

// Prevent session from starting for webhooks
define('NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';

use App\Services\WhatsAppService;
use App\Models\UserSettings;

// Enable error logging
logger("Webhook called at " . date('Y-m-d H:i:s'));

/**
 * Find the user by webhook verify token
 * This allows us to route the webhook to the correct tenant
 */
function findUserByWebhookToken($token)
{
    $userSettings = UserSettings::where('webhook_verify_token', $token)->first();
    if ($userSettings) {
        return $userSettings->user_id;
    }
    return null;
}

// Verify webhook (GET request from Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logger("[WEBHOOK VERIFY] Mode: {$mode}");
    logger("[WEBHOOK VERIFY] Received token: {$token}");
    
    // First try to find user by webhook token (multi-tenant)
    $userId = findUserByWebhookToken($token);
    
    // Fallback to environment variable (single tenant mode)
    if (!$userId && $token === env('WEBHOOK_VERIFY_TOKEN')) {
        // Assume this is single-tenant setup, use first user
        $firstUser = \App\Models\User::first();
        $userId = $firstUser ? $firstUser->id : null;
    }
    
    if (!$userId) {
        logger("[WEBHOOK VERIFY] ✗ Invalid token - no matching user found", 'error');
        http_response_code(403);
        exit;
    }
    
    logger("[WEBHOOK VERIFY] ✓ Found user ID: {$userId}");
    
    try {
        $whatsappService = new WhatsAppService($userId);
        $result = $whatsappService->verifyWebhook($mode, $token, $challenge);
        
        if ($result !== false) {
            logger("[WEBHOOK VERIFY] ✓ Verification successful");
            echo $result;
            exit;
        } else {
            logger("[WEBHOOK VERIFY] ✗ Verification failed", 'error');
            http_response_code(403);
            exit;
        }
    } catch (\Exception $e) {
        logger("[WEBHOOK VERIFY] ✗ Error: " . $e->getMessage(), 'error');
        http_response_code(403);
        exit;
    }
}

// Handle incoming webhook messages (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    logger("[WEBHOOK] Received POST request");
    logger("[WEBHOOK] Raw payload: " . substr($input, 0, 500) . (strlen($input) > 500 ? '...' : ''));
    
    $data = json_decode($input, true);
    
    if (!$data) {
        logger("[WEBHOOK ERROR] Invalid JSON received", 'error');
        http_response_code(400);
        exit;
    }
    
    logger("[WEBHOOK] JSON decoded successfully. Object type: " . ($data['object'] ?? 'unknown'));
    
    try {
        /**
         * Determine which user/tenant this webhook belongs to
         * We need to identify the user from the phone_number_id in the metadata
         */
        $userId = null;
        
        // Try to find user by phone number ID from webhook payload
        if (isset($data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'])) {
            $phoneNumberId = $data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
            $userSettings = UserSettings::where('whatsapp_phone_number_id', $phoneNumberId)->first();
            if ($userSettings) {
                $userId = $userSettings->user_id;
                logger("[WEBHOOK] Found user {$userId} by phone_number_id");
            }
        }
        
        // Fallback: use first user (single-tenant mode)
        if (!$userId) {
            $firstUser = \App\Models\User::first();
            if ($firstUser) {
                $userId = $firstUser->id;
                logger("[WEBHOOK] Using default user {$userId} (single-tenant fallback)");
            }
        }
        
        if (!$userId) {
            logger("[WEBHOOK ERROR] Could not determine user for webhook", 'error');
            http_response_code(400);
            exit;
        }
        
        // Create service with the identified user
        $whatsappService = new WhatsAppService($userId);
        
        // Process webhook data
        if (isset($data['entry'])) {
            logger("[WEBHOOK] Found " . count($data['entry']) . " entry/entries");
            
            foreach ($data['entry'] as $entryIndex => $entry) {
                logger("[WEBHOOK] Processing entry #{$entryIndex}, ID: " . ($entry['id'] ?? 'unknown'));
                
                if (isset($entry['changes'])) {
                    logger("[WEBHOOK] Found " . count($entry['changes']) . " change(s) in entry #{$entryIndex}");
                    
                    foreach ($entry['changes'] as $changeIndex => $change) {
                        $field = $change['field'] ?? 'unknown';
                        logger("[WEBHOOK] Change #{$changeIndex}: field = {$field}");
                        
                        if ($field === 'messages') {
                            logger("[WEBHOOK] Processing messages field...");
                            $result = $whatsappService->processWebhookMessage($change['value']);
                            logger("[WEBHOOK] Message processing result: " . ($result ? 'SUCCESS' : 'FAILED'));
                        } elseif ($field === 'message_status' || $field === 'messages_status') {
                            logger("[WEBHOOK] Processing message status update...");
                            $result = $whatsappService->processWebhookMessage($change['value']);
                            logger("[WEBHOOK] Status update result: " . ($result ? 'SUCCESS' : 'FAILED'));
                        }
                    }
                }
            }
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'success']);
        exit;
        
    } catch (\Exception $e) {
        logger("[WEBHOOK ERROR] Exception: " . $e->getMessage(), 'error');
        logger("[WEBHOOK ERROR] Stack: " . $e->getTraceAsString(), 'error');
        http_response_code(500);
        exit;
    }
}

// Invalid request method
http_response_code(405);


// Verify webhook (GET request from Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logger("[WEBHOOK VERIFY] Mode: {$mode}");
    logger("[WEBHOOK VERIFY] Received token: {$token}");
    logger("[WEBHOOK VERIFY] Expected token: " . env('WEBHOOK_VERIFY_TOKEN'));
    logger("[WEBHOOK VERIFY] Challenge: {$challenge}");
    
    $result = $whatsappService->verifyWebhook($mode, $token, $challenge);
    
    if ($result !== false) {
        logger("[WEBHOOK VERIFY] ✓ Verification successful");
        echo $result;
        exit;
    } else {
        logger("[WEBHOOK VERIFY] ✗ Verification failed", 'error');
        http_response_code(403);
        exit;
    }
}

// Handle incoming webhook messages (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    logger("[WEBHOOK] Received POST request");
    logger("[WEBHOOK] Raw payload: " . substr($input, 0, 500) . (strlen($input) > 500 ? '...' : ''));
    
    $data = json_decode($input, true);
    
    if (!$data) {
        logger("[WEBHOOK ERROR] Invalid JSON received", 'error');
        http_response_code(400);
        exit;
    }
    
    logger("[WEBHOOK] JSON decoded successfully. Object type: " . ($data['object'] ?? 'unknown'));
    
    try {
        // Process webhook data
        if (isset($data['entry'])) {
            logger("[WEBHOOK] Found " . count($data['entry']) . " entry/entries");
            
            foreach ($data['entry'] as $entryIndex => $entry) {
                logger("[WEBHOOK] Processing entry #{$entryIndex}, ID: " . ($entry['id'] ?? 'unknown'));
                
                if (isset($entry['changes'])) {
                    logger("[WEBHOOK] Found " . count($entry['changes']) . " change(s) in entry #{$entryIndex}");
                    
                    foreach ($entry['changes'] as $changeIndex => $change) {
                        $field = $change['field'] ?? 'unknown';
                        logger("[WEBHOOK] Change #{$changeIndex}: field = {$field}");
                        
                        if ($field === 'messages') {
                            logger("[WEBHOOK] Processing messages field...");
                            $result = $whatsappService->processWebhookMessage($change['value']);
                            logger("[WEBHOOK] Message processing result: " . ($result ? 'SUCCESS' : 'FAILED'));
                        } elseif ($field === 'message_status' || $field === 'messages_status') {
                            logger("[WEBHOOK] Processing message status update...");
                            $result = $whatsappService->processWebhookMessage($change['value']);
                            logger("[WEBHOOK] Status update result: " . ($result ? 'SUCCESS' : 'FAILED'));
                        } elseif ($field === 'history') {
                            logger("[WEBHOOK] Processing history field (conversation import)...");
                            logger("[WEBHOOK] History payload: " . json_encode($change['value']));
                            // History field contains conversation threads - extract messages
                            if (isset($change['value']['history'])) {
                                foreach ($change['value']['history'] as $historyItem) {
                                    if (isset($historyItem['threads'])) {
                                        foreach ($historyItem['threads'] as $thread) {
                                            if (isset($thread['messages'])) {
                                                logger("[WEBHOOK] Found " . count($thread['messages']) . " messages in history thread");
                                                // Convert history format to regular messages format
                                                $regularFormat = [
                                                    'messaging_product' => $change['value']['messaging_product'] ?? 'whatsapp',
                                                    'messages' => $thread['messages'],
                                                    'contacts' => [] // History might not have contact info
                                                ];
                                                $result = $whatsappService->processWebhookMessage($regularFormat);
                                                logger("[WEBHOOK] History messages result: " . ($result ? 'SUCCESS' : 'FAILED'));
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            logger("[WEBHOOK] Skipping field: {$field} - not configured for processing");
                        }
                    }
                } else {
                    logger("[WEBHOOK] No changes found in entry #{$entryIndex}", 'warning');
                }
            }
        } else {
            logger("[WEBHOOK] No 'entry' field in payload", 'warning');
        }
        
        // Always return 200 OK to Meta
        http_response_code(200);
        echo json_encode(['status' => 'received']);
    } catch (\Exception $e) {
        logger('Webhook processing error: ' . $e->getMessage(), 'error');
        http_response_code(200); // Still return 200 to Meta
        echo json_encode(['status' => 'error']);
    }
    
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
