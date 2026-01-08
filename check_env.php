<?php
/**
 * Check .env configuration
 */

require_once __DIR__ . '/bootstrap.php';

echo "Environment Configuration Check\n";
echo str_repeat('=', 50) . "\n\n";

$vars = [
    'APP_NAME',
    'APP_ENV',
    'APP_DEBUG',
    'DB_HOST',
    'DB_DATABASE',
    'DB_USERNAME',
    'WHATSAPP_ACCESS_TOKEN',
    'WHATSAPP_PHONE_NUMBER_ID',
    'WEBHOOK_VERIFY_TOKEN'
];

foreach ($vars as $var) {
    $value = env($var);
    if ($var === 'WHATSAPP_ACCESS_TOKEN' && $value) {
        // Mask access token
        $value = substr($value, 0, 10) . '...' . substr($value, -5);
    }
    if ($var === 'DB_PASSWORD' && $value) {
        $value = '****';
    }
    
    $status = $value ? '✓' : '✗';
    echo "{$status} {$var}: " . ($value ?: 'NOT SET') . "\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Webhook Verify Token Details:\n";
echo "Value: '" . env('WEBHOOK_VERIFY_TOKEN') . "'\n";
echo "Length: " . strlen(env('WEBHOOK_VERIFY_TOKEN') ?: '') . "\n";
echo "Type: " . gettype(env('WEBHOOK_VERIFY_TOKEN')) . "\n";
