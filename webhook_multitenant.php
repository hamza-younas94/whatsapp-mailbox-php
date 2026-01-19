<?php
/**
 * Multi-Tenant Webhook Handler
 * 
 * Routes incoming webhooks to the correct user (tenant) based on phone_number_id
 */

require_once __DIR__ . '/bootstrap.php';

use App\Services\MultiTenantWhatsAppService;
use App\Models\UserApiCredential;

header('Content-Type: application/json');

logger("Webhook called at " . date('Y-m-d H:i:s'));

// Handle GET request (webhook verification)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    logger("[WEBHOOK] Received GET request (verification)");
    
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logger("[VERIFY] Mode: {$mode}, Token: {$token}");
    
    // Try to find matching credentials by verify token
    $credentials = UserApiCredential::where('webhook_verify_token', $token)
        ->where('is_active', true)
        ->first();
    
    if ($credentials && $mode === 'subscribe') {
        logger("[VERIFY] ✓ Token verified for user {$credentials->user_id}");
        echo $challenge;
        exit;
    }
    
    logger("[VERIFY] ✗ Token verification failed", 'error');
    http_response_code(403);
    echo json_encode(['error' => 'Verification failed']);
    exit;
}

// Handle POST request (incoming message/status update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logger("[WEBHOOK] Received POST request");
    
    $rawPayload = file_get_contents('php://input');
    logger("[WEBHOOK] Raw payload: " . $rawPayload);
    
    $payload = json_decode($rawPayload, true);
    
    if (!$payload) {
        logger("[WEBHOOK] Failed to decode JSON payload", 'error');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    $objectType = $payload['object'] ?? null;
    logger("[WEBHOOK] JSON decoded successfully. Object type: {$objectType}");
    
    if ($objectType !== 'whatsapp_business_account') {
        logger("[WEBHOOK] Invalid object type: {$objectType}", 'warning');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid object type']);
        exit;
    }
    
    $entries = $payload['entry'] ?? [];
    $entryCount = count($entries);
    logger("[WEBHOOK] Found {$entryCount} entry/entries");
    
    foreach ($entries as $entryIndex => $entry) {
        $entryId = $entry['id'] ?? 'unknown';
        logger("[WEBHOOK] Processing entry #{$entryIndex}, ID: {$entryId}");
        
        $changes = $entry['changes'] ?? [];
        $changeCount = count($changes);
        logger("[WEBHOOK] Found {$changeCount} change(s) in entry #{$entryIndex}");
        
        foreach ($changes as $changeIndex => $change) {
            $field = $change['field'] ?? '';
            logger("[WEBHOOK] Change #{$changeIndex}: field = {$field}");
            
            if ($field !== 'messages') {
                logger("[WEBHOOK] Skipping non-message field: {$field}", 'info');
                continue;
            }
            
            $value = $change['value'] ?? [];
            
            // ============================================
            // MULTI-TENANT ROUTING: Identify user by phone_number_id
            // ============================================
            $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
            
            if (!$phoneNumberId) {
                logger("[WEBHOOK] No phone_number_id found in metadata", 'error');
                continue;
            }
            
            logger("[WEBHOOK] Phone Number ID: {$phoneNumberId}");
            
            // Find the user (tenant) who owns this phone number ID
            $credentials = UserApiCredential::findByPhoneNumberId($phoneNumberId);
            
            if (!$credentials) {
                logger("[WEBHOOK] No user found for phone_number_id: {$phoneNumberId}", 'error');
                continue;
            }
            
            logger("[WEBHOOK] Routing to user {$credentials->user_id} ({$credentials->business_name})");
            
            try {
                // Create service for this specific user
                $service = new MultiTenantWhatsAppService($credentials->user_id);
                
                // Process the message
                logger("[WEBHOOK] Processing messages field...");
                $result = $service->processWebhookMessage($value);
                
                if ($result) {
                    logger("[WEBHOOK] Message processing result: SUCCESS");
                } else {
                    logger("[WEBHOOK] Message processing result: FAILED", 'error');
                }
                
            } catch (\Exception $e) {
                logger("[WEBHOOK] Error creating service: " . $e->getMessage(), 'error');
                logger("[WEBHOOK] Stack trace: " . $e->getTraceAsString(), 'error');
            }
        }
    }
    
    // Always return 200 OK to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'received']);
    exit;
}

// Invalid request method
logger("[WEBHOOK] Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'error');
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
