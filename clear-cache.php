<?php
/**
 * Clear Server Cache Utility
 * Use this to clear any server-side caching issues
 */

// Clear OPcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    $opcache = '✅ OPcache cleared';
} else {
    $opcache = '⚠️ OPcache not available';
}

// Clear APCu cache if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    $apcu = '✅ APCu cache cleared';
} else {
    $apcu = '⚠️ APCu not available';
}

// Clear stat cache
clearstatcache(true);
$statcache = '✅ Stat cache cleared';

// Get template cache directory
$cacheDir = __DIR__ . '/cache/';
$cacheCleared = false;
$cacheFiles = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $cacheFiles++;
        }
    }
    $cacheCleared = true;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Cleared</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        h1 {
            color: #1a202c;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .result {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .result-item {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .result-item:last-child {
            border-bottom: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 5px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #1a202c;
        }
        .instructions {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: left;
            font-size: 14px;
        }
        .instructions strong {
            display: block;
            margin-bottom: 10px;
            color: #92400e;
        }
        .instructions ol {
            margin-left: 20px;
        }
        .instructions li {
            margin: 5px 0;
            color: #78350f;
        }
        .timestamp {
            color: #a0aec0;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🧹</div>
        <h1>Cache Cleared Successfully!</h1>
        <p class="subtitle">Server-side caches have been cleared</p>
        
        <div class="result">
            <div class="result-item"><?php echo $opcache; ?></div>
            <div class="result-item"><?php echo $apcu; ?></div>
            <div class="result-item"><?php echo $statcache; ?></div>
            <?php if ($cacheCleared): ?>
                <div class="result-item">✅ Template cache cleared (<?php echo $cacheFiles; ?> files removed)</div>
            <?php else: ?>
                <div class="result-item">⚠️ No template cache directory found</div>
            <?php endif; ?>
        </div>
        
        <div class="instructions">
            <strong>🔄 Now Clear Your Browser Cache:</strong>
            <ol>
                <li><strong>Hard Refresh:</strong> Press Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)</li>
                <li><strong>Clear Browser Cache:</strong> Go to Settings → Privacy → Clear browsing data</li>
                <li><strong>Or Use Incognito:</strong> Open the site in a new incognito/private window</li>
                <li><strong>Check Console:</strong> Press F12 to open DevTools and check for errors</li>
            </ol>
        </div>
        
        <a href="index.php" class="btn">← Back to Mailbox</a>
        <a href="check-media.php" class="btn btn-secondary">Run Diagnostics</a>
        
        <div class="timestamp">
            Cleared at: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>
