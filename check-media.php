<?php
/**
 * Media Feature Diagnostic Page
 * Check if all media-related features are properly deployed
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}


header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Feature Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: #f5f7fa; 
            padding: 40px 20px; 
        }
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 12px; 
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #1a202c; margin-bottom: 10px; }
        .subtitle { color: #718096; margin-bottom: 30px; }
        .check-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            align-items: start;
            gap: 15px;
        }
        .check-item.success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
        }
        .check-item.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        .check-item.warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
        }
        .icon {
            font-size: 24px;
            line-height: 1;
        }
        .check-content {
            flex: 1;
        }
        .check-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .check-desc {
            font-size: 14px;
            color: #64748b;
        }
        .code {
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin-top: 10px;
            overflow-x: auto;
        }
        .section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .section h2 {
            color: #1a202c;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 20px;
        }
        .btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Media Feature Diagnostic</h1>
        <p class="subtitle">Checking if all Phase 1 features are properly deployed...</p>

        <?php
        $checks = [];
        
        // 1. Check if uploads directory exists and is writable
        $uploadsDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadsDir)) {
            $checks[] = [
                'status' => 'error',
                'title' => 'Uploads Directory Missing',
                'desc' => 'The uploads directory does not exist. Creating it now...',
                'code' => 'Path: ' . $uploadsDir
            ];
            if (!mkdir($uploadsDir, 0755, true)) {
                $checks[count($checks)-1]['desc'] .= ' FAILED to create directory!';
            } else {
                $checks[count($checks)-1]['status'] = 'success';
                $checks[count($checks)-1]['desc'] = 'Uploads directory created successfully!';
            }
        } else {
            $isWritable = is_writable($uploadsDir);
            $checks[] = [
                'status' => $isWritable ? 'success' : 'error',
                'title' => 'Uploads Directory',
                'desc' => $isWritable ? 'Exists and is writable' : 'Exists but is NOT writable (chmod 755 needed)',
                'code' => 'Path: ' . $uploadsDir . "\nPermissions: " . substr(sprintf('%o', fileperms($uploadsDir)), -4)
            ];
        }
        
        // 2. Check if WhatsAppService has sendMediaMessage method
        $serviceFile = __DIR__ . '/app/Services/WhatsAppService.php';
        if (file_exists($serviceFile)) {
            $content = file_get_contents($serviceFile);
            $hasMethod = strpos($content, 'function sendMediaMessage') !== false;
            $checks[] = [
                'status' => $hasMethod ? 'success' : 'error',
                'title' => 'WhatsAppService::sendMediaMessage()',
                'desc' => $hasMethod ? 'Method exists in WhatsAppService' : 'Method NOT FOUND in WhatsAppService',
                'code' => 'File: ' . $serviceFile
            ];
        } else {
            $checks[] = [
                'status' => 'error',
                'title' => 'WhatsAppService File Missing',
                'desc' => 'WhatsAppService.php not found',
                'code' => 'Expected: ' . $serviceFile
            ];
        }
        
        // 3. Check if API has send-media endpoint
        $apiFile = __DIR__ . '/api.php';
        if (file_exists($apiFile)) {
            $content = file_get_contents($apiFile);
            $hasEndpoint = strpos($content, "case 'send-media':") !== false;
            $checks[] = [
                'status' => $hasEndpoint ? 'success' : 'error',
                'title' => 'API Endpoint: send-media',
                'desc' => $hasEndpoint ? 'Endpoint exists in api.php' : 'Endpoint NOT FOUND in api.php',
                'code' => 'File: ' . $apiFile
            ];
        }
        
        // 4. Check if JavaScript has media functions
        $jsFile = __DIR__ . '/assets/js/app.js';
        if (file_exists($jsFile)) {
            $content = file_get_contents($jsFile);
            $hasHandleFile = strpos($content, 'function handleFileSelect') !== false;
            $hasClearMedia = strpos($content, 'function clearMediaSelection') !== false;
            $hasSelectedMedia = strpos($content, 'let selectedMediaFile') !== false;
            
            $allPresent = $hasHandleFile && $hasClearMedia && $hasSelectedMedia;
            $checks[] = [
                'status' => $allPresent ? 'success' : 'error',
                'title' => 'JavaScript Media Functions',
                'desc' => $allPresent ? 'All media functions found' : 'Some functions missing',
                'code' => 'handleFileSelect: ' . ($hasHandleFile ? '✓' : '✗') . "\n" .
                         'clearMediaSelection: ' . ($hasClearMedia ? '✓' : '✗') . "\n" .
                         'selectedMediaFile: ' . ($hasSelectedMedia ? '✓' : '✗')
            ];
            
            // Get file modification time
            $modTime = filemtime($jsFile);
            $checks[] = [
                'status' => 'success',
                'title' => 'JavaScript File Last Modified',
                'desc' => 'File was last modified: ' . date('Y-m-d H:i:s', $modTime),
                'code' => 'If this date is old, the file may not have been updated on the server'
            ];
        }
        
        // 5. Check CSS for media preview styles
        $cssFile = __DIR__ . '/assets/css/style.css';
        if (file_exists($cssFile)) {
            $content = file_get_contents($cssFile);
            $hasMediaPreview = strpos($content, '.media-preview') !== false;
            $hasAttachBtn = strpos($content, '.attach-btn') !== false;
            
            $checks[] = [
                'status' => ($hasMediaPreview && $hasAttachBtn) ? 'success' : 'error',
                'title' => 'CSS Media Styles',
                'desc' => ($hasMediaPreview && $hasAttachBtn) ? 'Media preview styles found' : 'Media styles missing',
                'code' => '.media-preview: ' . ($hasMediaPreview ? '✓' : '✗') . "\n" .
                         '.attach-btn: ' . ($hasAttachBtn ? '✓' : '✗')
            ];
            
            $modTime = filemtime($cssFile);
            $checks[] = [
                'status' => 'success',
                'title' => 'CSS File Last Modified',
                'desc' => 'File was last modified: ' . date('Y-m-d H:i:s', $modTime),
                'code' => 'If this date is old, the CSS may not have been updated'
            ];
        }
        
        // 6. Check dashboard template
        $templateFile = __DIR__ . '/templates/dashboard.html.twig';
        if (file_exists($templateFile)) {
            $content = file_get_contents($templateFile);
            $hasMediaInput = strpos($content, 'id="mediaInput"') !== false;
            $hasAttachBtn = strpos($content, 'class="attach-btn"') !== false;
            $hasMediaPreview = strpos($content, 'id="mediaPreview"') !== false;
            
            $allPresent = $hasMediaInput && $hasAttachBtn && $hasMediaPreview;
            $checks[] = [
                'status' => $allPresent ? 'success' : 'error',
                'title' => 'Dashboard Template Elements',
                'desc' => $allPresent ? 'All media UI elements found in template' : 'Some UI elements missing',
                'code' => 'mediaInput: ' . ($hasMediaInput ? '✓' : '✗') . "\n" .
                         'attach-btn: ' . ($hasAttachBtn ? '✓' : '✗') . "\n" .
                         'mediaPreview: ' . ($hasMediaPreview ? '✓' : '✗')
            ];
        }
        
        // 7. Check database for media columns
        try {
            require_once __DIR__ . '/bootstrap.php';
            
            $columns = Capsule::schema()->getColumnListing('messages');
            $hasMediaId = in_array('media_id', $columns);
            $hasMediaFilename = in_array('media_filename', $columns);
            $hasMediaSize = in_array('media_size', $columns);
            
            $allPresent = $hasMediaId && $hasMediaFilename && $hasMediaSize;
            $checks[] = [
                'status' => $allPresent ? 'success' : 'warning',
                'title' => 'Database Media Columns',
                'desc' => $allPresent ? 'All media columns exist in messages table' : 'Some media columns missing (run migrations)',
                'code' => 'media_id: ' . ($hasMediaId ? '✓' : '✗') . "\n" .
                         'media_filename: ' . ($hasMediaFilename ? '✓' : '✗') . "\n" .
                         'media_size: ' . ($hasMediaSize ? '✓' : '✗')
            ];
        } catch (Exception $e) {
            $checks[] = [
                'status' => 'error',
                'title' => 'Database Check Failed',
                'desc' => 'Could not check database: ' . $e->getMessage(),
                'code' => ''
            ];
        }
        
        // Display results
        foreach ($checks as $check) {
            echo '<div class="check-item ' . $check['status'] . '">';
            echo '<div class="icon">' . ($check['status'] === 'success' ? '✅' : ($check['status'] === 'error' ? '❌' : '⚠️')) . '</div>';
            echo '<div class="check-content">';
            echo '<div class="check-title">' . htmlspecialchars($check['title']) . '</div>';
            echo '<div class="check-desc">' . htmlspecialchars($check['desc']) . '</div>';
            if (!empty($check['code'])) {
                echo '<div class="code">' . htmlspecialchars($check['code']) . '</div>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        // Count results
        $successCount = count(array_filter($checks, fn($c) => $c['status'] === 'success'));
        $totalCount = count($checks);
        
        echo '<div class="section">';
        echo '<h2>Summary</h2>';
        echo '<p>Passed: ' . $successCount . ' / ' . $totalCount . ' checks</p>';
        
        if ($successCount === $totalCount) {
            echo '<div class="check-item success">';
            echo '<div class="icon">🎉</div>';
            echo '<div class="check-content">';
            echo '<div class="check-title">All Checks Passed!</div>';
            echo '<div class="check-desc">All media features are properly deployed. If you still can\'t see them:</div>';
            echo '<div class="code">';
            echo '1. Hard refresh your browser: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)<br>';
            echo '2. Clear browser cache completely<br>';
            echo '3. Try incognito/private browsing mode<br>';
            echo '4. Check browser console for JavaScript errors (F12)<br>';
            echo '5. Verify you\'re on the correct URL (check-media.php is at: ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . ')';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="check-item error">';
            echo '<div class="icon">⚠️</div>';
            echo '<div class="check-content">';
            echo '<div class="check-title">Some Issues Found</div>';
            echo '<div class="check-desc">Please fix the errors above. If files are missing, you may need to re-deploy from GitHub.</div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        ?>
        
        <div class="section">
            <h2>🛠️ Quick Fixes</h2>
            <div class="check-item warning">
                <div class="icon">💡</div>
                <div class="check-content">
                    <div class="check-title">Cache Busting Test</div>
                    <div class="check-desc">The dashboard template already includes cache-busting parameters. Current timestamp:</div>
                    <div class="code">?v=<?php echo time(); ?></div>
                    <div class="check-desc" style="margin-top: 10px;">Your asset URLs should look like:<br>
                    assets/css/style.css?v=<?php echo time(); ?><br>
                    assets/js/app.js?v=<?php echo time(); ?></div>
                </div>
            </div>
            
            <div class="check-item warning">
                <div class="icon">🔄</div>
                <div class="check-content">
                    <div class="check-title">Pull Latest Changes</div>
                    <div class="check-desc">If files are outdated, pull from GitHub:</div>
                    <div class="code">cd /path/to/whatsapp-mailbox<br>git pull origin main</div>
                </div>
            </div>
        </div>

        <a href="index.php" class="btn">← Back to Mailbox</a>
    </div>
</body>
</html>
