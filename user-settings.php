<?php
/**
 * User Settings Page - Configure API Credentials
 */

require_once __DIR__ . '/bootstrap.php';

// Require authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$user = \App\Models\User::find($userId);

if (!$user) {
    die('User not found');
}

// Get or create user settings
$userSettings = \App\Models\UserSettings::firstOrCreate(
    ['user_id' => $userId],
    [
        'webhook_verify_token' => bin2hex(random_bytes(32))
    ]
);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'whatsapp_access_token' => $_POST['whatsapp_access_token'] ?? '',
        'whatsapp_phone_number_id' => $_POST['whatsapp_phone_number_id'] ?? '',
        'phone_number' => $_POST['phone_number'] ?? '',
        'business_name' => $_POST['business_name'] ?? '',
        'whatsapp_api_version' => $_POST['whatsapp_api_version'] ?? 'v18.0'
    ];
    
    // Mark as configured only if both token and phone_number_id are present
    if (!empty($data['whatsapp_access_token']) && !empty($data['whatsapp_phone_number_id'])) {
        $data['is_configured'] = true;
    }
    
    $userSettings->update($data);
    
    $message = 'Settings updated successfully!';
    $messageType = 'success';
}

// Generate new webhook token
if (isset($_POST['generate_token'])) {
    $newToken = $userSettings->generateWebhookToken();
    $message = 'New webhook token generated!';
    $messageType = 'success';
}

// Render page
$twig = require_once __DIR__ . '/bootstrap.php';
echo $twig->render('user-settings.html.twig', [
    'user' => $user,
    'settings' => $userSettings,
    'message' => $message,
    'messageType' => $messageType,
    'webhookUrl' => $_SERVER['HTTP_HOST'] . '/webhook.php'
]);
