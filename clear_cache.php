<?php
// Clear Twig template cache

echo "🗑️  Clearing Twig Template Cache...\n\n";

$cacheDir = __DIR__ . '/cache';

if (is_dir($cacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    $count = 0;
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
        $count++;
    }
    
    echo "✅ Deleted $count cached template files\n";
    echo "✅ Cache directory cleared!\n\n";
} else {
    echo "ℹ️  No cache directory found (that's okay)\n\n";
}

// Also check for any opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ PHP OpCache cleared!\n\n";
}

echo "🎯 Now refresh your browser!\n";
echo "   The CSS will load with the correct relative paths.\n";
