<?php
/**
 * Direct webhook test without routing
 */

define('NO_SESSION', true);
require_once __DIR__ . '/bootstrap.php';

use App\Services\WhatsAppService;

// Simulate GET request for verification
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['hub_mode'] = 'subscribe';
$_GET['hub_verify_token'] = 'hamza';
$_GET['hub_challenge'] = 'test_challenge_12345';

echo "Testing webhook verification directly...\n\n";

$whatsappService = new WhatsAppService();

logger("[TEST] Starting direct webhook verification test");

$mode = $_GET['hub_mode'] ?? '';
$token = $_GET['hub_verify_token'] ?? '';
$challenge = $_GET['hub_challenge'] ?? '';

logger("[TEST WEBHOOK VERIFY] Mode: {$mode}");
logger("[TEST WEBHOOK VERIFY] Received token: {$token}");
logger("[TEST WEBHOOK VERIFY] Expected token: " . env('WEBHOOK_VERIFY_TOKEN'));
logger("[TEST WEBHOOK VERIFY] Challenge: {$challenge}");

$result = $whatsappService->verifyWebhook($mode, $token, $challenge);

if ($result !== false) {
    logger("[TEST WEBHOOK VERIFY] ✓ Verification successful");
    echo "✓ Verification PASSED\n";
    echo "Challenge returned: {$result}\n";
} else {
    logger("[TEST WEBHOOK VERIFY] ✗ Verification failed", 'error');
    echo "✗ Verification FAILED\n";
}

echo "\nCheck storage/logs/app.log for detailed logs\n";
