<?php
/**
 * RAW Webhook Logger - Captures EVERYTHING
 * Use this temporarily to see exactly what Facebook is sending
 */

// Log file path
$logFile = __DIR__ . '/storage/logs/webhook_raw_' . date('Y-m-d') . '.log';

// Ensure directory exists
if (!is_dir(__DIR__ . '/storage/logs')) {
    mkdir(__DIR__ . '/storage/logs', 0755, true);
}

// Current timestamp
$timestamp = date('Y-m-d H:i:s');

// Capture everything
$logData = [
    'timestamp' => $timestamp,
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'query_params' => $_GET,
    'post_params' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'server' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]
];

// Write to log file
$logEntry = str_repeat('=', 80) . "\n";
$logEntry .= "WEBHOOK REQUEST - {$timestamp}\n";
$logEntry .= str_repeat('=', 80) . "\n";
$logEntry .= json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Handle GET verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    if ($mode === 'subscribe' && $token === 'apple') {
        echo $challenge;
        exit;
    }
    
    http_response_code(403);
    exit;
}

// Handle POST - always return 200
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(200);
    echo json_encode(['status' => 'logged']);
    exit;
}

http_response_code(405);
