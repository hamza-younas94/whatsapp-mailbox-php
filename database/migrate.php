<?php
/**
 * Database Migration Runner
 * Run this file to execute all migrations
 */

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

echo "Starting migration process...\n";
flush();

try {
    require_once __DIR__ . '/../bootstrap.php';
    echo "Bootstrap loaded successfully.\n";
    flush();
} catch (Exception $e) {
    echo "ERROR loading bootstrap: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

use Illuminate\Database\Capsule\Manager as Capsule;

echo "\nRunning migrations...\n\n";
flush();

$migrationsPath = __DIR__ . '/migrations';

if (!is_dir($migrationsPath)) {
    echo "ERROR: Migrations directory not found at: $migrationsPath\n";
    exit(1);
}

$migrations = glob($migrationsPath . '/*.php');

if (empty($migrations)) {
    echo "ERROR: No migration files found in: $migrationsPath\n";
    exit(1);
}

sort($migrations);

echo "Found " . count($migrations) . " migration(s).\n\n";
flush();

$successCount = 0;
$failCount = 0;

foreach ($migrations as $migration) {
    $migrationName = basename($migration);
    echo "Running: {$migrationName}... ";
    flush();
    
    try {
        $migrationClass = require $migration;
        
        if (!is_object($migrationClass)) {
            throw new Exception("Migration file must return an object");
        }
        
        if (!method_exists($migrationClass, 'up')) {
            throw new Exception("Migration class must have an 'up' method");
        }
        
        $migrationClass->up();
        echo "✓ Done\n";
        flush();
        $successCount++;
    } catch (Exception $e) {
        echo "✗ Failed\n";
        echo "  Error: " . $e->getMessage() . "\n";
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "  Stack trace: " . $e->getTraceAsString() . "\n";
        }
        flush();
        $failCount++;
    }
}

echo "\n" . str_repeat('-', 50) . "\n";
echo "Migrations completed!\n";
echo "Success: {$successCount} | Failed: {$failCount}\n";
echo str_repeat('-', 50) . "\n";

if ($failCount > 0) {
    echo "\nNote: Some migrations failed. Tables might already exist.\n";
    echo "This is normal if you're re-running migrations.\n";
}

exit(0);
