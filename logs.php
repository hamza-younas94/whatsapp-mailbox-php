<?php
require_once 'bootstrap.php';
require_once 'auth.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only admins can view logs
$user = getCurrentUser();
if (!$user || !isAdmin()) {
    header('Location: index.php');
    exit;
}

$logDir = __DIR__ . '/storage/logs';
$logFiles = [];
$selectedFile = $_GET['file'] ?? 'app.log';
$selectedLevel = $_GET['level'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Get all log files
if (is_dir($logDir)) {
    $files = scandir($logDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'log') {
            $logFiles[] = $file;
        }
    }
    rsort($logFiles); // Most recent first
}

// Read log file
$logContent = [];
$logLines = [];
if (in_array($selectedFile, $logFiles)) {
    $filePath = $logDir . '/' . $selectedFile;
    if (file_exists($filePath)) {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Parse log lines (Monolog format: [YYYY-MM-DD HH:MM:SS] level.LEVEL: message)
        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+(\w+)\.(\w+):\s+(.+)$/', $line, $matches)) {
                $timestamp = $matches[1];
                $channel = $matches[2];
                $level = strtoupper($matches[3]);
                $message = $matches[4];
                
                // Filter by level
                if ($selectedLevel !== 'all' && strtolower($level) !== strtolower($selectedLevel)) {
                    continue;
                }
                
                // Filter by search query
                if ($searchQuery && stripos($message, $searchQuery) === false) {
                    continue;
                }
                
                $logLines[] = [
                    'timestamp' => $timestamp,
                    'channel' => $channel,
                    'level' => $level,
                    'message' => $message,
                    'raw' => $line
                ];
            } else {
                // Non-standard format, include as-is
                if ($searchQuery && stripos($line, $searchQuery) === false) {
                    continue;
                }
                $logLines[] = [
                    'timestamp' => '',
                    'channel' => '',
                    'level' => 'INFO',
                    'message' => $line,
                    'raw' => $line
                ];
            }
        }
        
        // Reverse to show most recent first
        $logLines = array_reverse($logLines);
        
        // Limit to last 1000 lines for performance
        $logLines = array_slice($logLines, 0, 1000);
    }
}

// Get file size
$fileSize = 0;
if (in_array($selectedFile, $logFiles)) {
    $filePath = $logDir . '/' . $selectedFile;
    if (file_exists($filePath)) {
        $fileSize = filesize($filePath);
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Render logs page using Twig
render('logs.html.twig', [
    'logFiles' => $logFiles,
    'selectedFile' => $selectedFile,
    'selectedLevel' => $selectedLevel,
    'searchQuery' => $searchQuery,
    'logLines' => $logLines,
    'fileSize' => $fileSize,
    'formattedSize' => formatBytes($fileSize),
    'user' => $user
]);

