<?php
/**
 * DCMB IP Commands - View IP command history
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use App\Models\IpCommand;

if (!isAuthenticated()) {
    redirect('/login.php');
}

$user = getCurrentUser();

requireFeature('dcmb_ip_commands');

// Handle manual IP submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    try {
        $ipAddress = trim($_POST['ip_address'] ?? '');

        if (empty($ipAddress)) {
            echo json_encode(['success' => false, 'error' => 'IP address is required']);
            exit;
        }

        // Basic validation for IPv4/IPv6
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            echo json_encode(['success' => false, 'error' => 'Invalid IP address']);
            exit;
        }
        
        // Call the analytics API
        $apiUrl = "https://analytics.dealcart.io/add-ip/{$ipAddress}";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $apiResponse = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($apiResponse === false && $curlErr) {
            $apiResponse = $curlErr;
            $httpCode = 0;
        }
        
        // Save to database
        $ipCommand = IpCommand::create([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'contact_name' => 'Admin Manual',
            'phone_number' => null,
            'api_response' => $apiResponse,
            'http_code' => $httpCode,
            'status' => ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'failed'
        ]);
        
        echo json_encode([
            'success' => true,
            'response' => $apiResponse,
            'http_code' => $httpCode
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get all IP commands (scoped to user)
$ipCommands = IpCommand::where('user_id', $user->id)
    ->orderBy('created_at', 'desc')
    ->limit(100)
    ->get();

// Stats (scoped to user)
$totalCommands = IpCommand::where('user_id', $user->id)->count();
$successCount = IpCommand::where('user_id', $user->id)->where('status', 'success')->count();
$failedCount = IpCommand::where('user_id', $user->id)->where('status', 'failed')->count();

echo render('ip_commands.html.twig', [
    'ipCommands' => $ipCommands,
    'totalCommands' => $totalCommands,
    'successCount' => $successCount,
    'failedCount' => $failedCount,
    'user' => $user
]);
