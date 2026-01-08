<?php
/**
 * Verify that files are the latest version
 */

echo "File Version Check\n";
echo str_repeat('=', 50) . "\n\n";

// Check webhook.php
$webhookContent = file_get_contents(__DIR__ . '/webhook.php');
if (strpos($webhookContent, '[WEBHOOK VERIFY]') !== false) {
    echo "✓ webhook.php has NEW logging code\n";
} else {
    echo "✗ webhook.php still has OLD code\n";
}

// Check WhatsAppService.php
$serviceContent = file_get_contents(__DIR__ . '/app/Services/WhatsAppService.php');
if (strpos($serviceContent, '[SERVICE]') !== false) {
    echo "✓ WhatsAppService.php has NEW logging code\n";
} else {
    echo "✗ WhatsAppService.php still has OLD code\n";
}

if (strpos($serviceContent, '[SAVE]') !== false) {
    echo "✓ WhatsAppService.php has detailed SAVE logging\n";
} else {
    echo "✗ WhatsAppService.php missing SAVE logging\n";
}

if (strpos($serviceContent, '[CONTACT]') !== false) {
    echo "✓ WhatsAppService.php has CONTACT logging\n";
} else {
    echo "✗ WhatsAppService.php missing CONTACT logging\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "If any checks show OLD code, re-upload those files.\n";
