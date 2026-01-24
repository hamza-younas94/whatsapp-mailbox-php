<?php
/**
 * User Settings Page - Configure API Credentials
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/app/helpers.php';
use App\Services\Encryption;
use App\Validation;

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

// Enforce feature access for settings
requireFeature('settings');

// Get or create user settings
$userSettings = \App\Models\UserSettings::firstOrCreate(
    ['user_id' => $userId],
    [
        'webhook_verify_token' => bin2hex(random_bytes(32))
    ]
);

// Decrypt sensitive fields for display (backward compatible)
$userSettings->whatsapp_access_token = Encryption::decrypt($userSettings->whatsapp_access_token);
$userSettings->whatsapp_phone_number_id = Encryption::decrypt($userSettings->whatsapp_phone_number_id);

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'whatsapp_access_token' => trim($_POST['whatsapp_access_token'] ?? ''),
        'whatsapp_phone_number_id' => trim($_POST['whatsapp_phone_number_id'] ?? ''),
        'phone_number' => $_POST['phone_number'] ?? '',
        'business_name' => $_POST['business_name'] ?? '',
        'whatsapp_api_version' => $_POST['whatsapp_api_version'] ?? 'v18.0'
    ];
    
    $validator = new Validation($input);
    if (!$validator->validate([
        'business_name' => 'max:150',
        'phone_number' => 'max:20',
        'whatsapp_api_version' => 'required|in:v18.0,v17.0,v16.0'
    ])) {
        $message = 'Validation failed: ' . implode(', ', array_map(fn($e) => reset($e), $validator->errors()));
        $messageType = 'danger';
    } else {
        $data = [
            'whatsapp_access_token' => Encryption::encrypt($input['whatsapp_access_token']),
            'whatsapp_phone_number_id' => Encryption::encrypt($input['whatsapp_phone_number_id']),
            'phone_number' => Validation::sanitize($input['phone_number']),
            'business_name' => Validation::sanitize($input['business_name']),
            'whatsapp_api_version' => $input['whatsapp_api_version']
        ];
    
        // Mark as configured only if both token and phone_number_id are present
        if (!empty($data['whatsapp_access_token']) && !empty($data['whatsapp_phone_number_id'])) {
            $data['is_configured'] = true;
        }
        
        $userSettings->update($data);
        
        $message = 'Settings updated successfully!';
        $messageType = 'success';
    }
}

// Generate new webhook token
if (isset($_POST['generate_token'])) {
    $newToken = $userSettings->generateWebhookToken();
    $message = 'New webhook token generated!';
    $messageType = 'success';
}

// Render page
render('user-settings.html.twig', [
    'user' => $user,
    'settings' => $userSettings,
    'message' => $message,
    'messageType' => $messageType,
    'webhookUrl' => $_SERVER['HTTP_HOST'] . '/webhook.php'
]);
