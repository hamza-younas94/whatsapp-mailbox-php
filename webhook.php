<?php
/**
 * WhatsApp Webhook Handler with Eloquent ORM
 */

// Prevent session from starting for webhooks
define('NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';

use App\Services\WhatsAppService;

// Enable error logging
logger("Webhook called at " . date('Y-m-d H:i:s'));

$whatsappService = new WhatsAppService();

// Verify webhook (GET request from Meta)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    $result = $whatsappService->verifyWebhook($mode, $token, $challenge);
    
    if ($result !== false) {
        logger("Webhook verified successfully");
        echo $result;
        exit;
    } else {
        logger("Webhook verification failed");
        http_response_code(403);
        exit;
    }
}

// Handle incoming webhook messages (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    logger("Webhook received: " . $input);
    
    $data = json_decode($input, true);
    
    if (!$data) {
        logger("Invalid JSON received");
        http_response_code(400);
        exit;
    }
    
    try {
        // Process webhook data
        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if ($change['field'] === 'messages') {
                            $whatsappService->processWebhookMessage($change['value']);
                        }
                    }
                }
            }
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
