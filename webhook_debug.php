<?php
/**
 * Webhook Debug - Capture ALL incoming requests
 */

// Log everything to a separate file for debugging
$debugLog = __DIR__ . '/storage/logs/webhook_debug.log';

$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'] ?? 'unknown';

$logData = [
    'timestamp' => $timestamp,
    'method' => $method,
    'uri' => $uri,
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'server' => [
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    ]
];

// Log to debug file
file_put_contents(
    $debugLog,
    "\n" . str_repeat('=', 80) . "\n" .
    "WEBHOOK REQUEST - {$timestamp}\n" .
    str_repeat('=', 80) . "\n" .
    json_encode($logData, JSON_PRETTY_PRINT) . "\n",
    FILE_APPEND
);

// Now process with normal webhook
define('NO_SESSION', true);
require_once __DIR__ . '/bootstrap.php';

use App\Services\WhatsAppService;

logger("[WEBHOOK DEBUG] Request method: {$method}");
logger("[WEBHOOK DEBUG] Raw input length: " . strlen($logData['raw_input']));

// Use first user for webhook debug (no session)
$userId = \Illuminate\Database\Capsule\Manager::table('users')->orderBy('id')->value('id') ?? 1;
$whatsappService = new WhatsAppService($userId);

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
                        } else {
                            logger("[WEBHOOK] Skipping field: {$field}");
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
        logger("[WEBHOOK] Sending 200 OK response");
        http_response_code(200);
        echo json_encode(['status' => 'received']);
    } catch (\Exception $e) {
        logger('[WEBHOOK ERROR] Exception: ' . $e->getMessage(), 'error');
        logger('[WEBHOOK ERROR] Stack trace: ' . $e->getTraceAsString(), 'error');
        http_response_code(200); // Still return 200 to Meta
        echo json_encode(['status' => 'error']);
    }
    
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
