<?php
/**
 * Database Migration Runner
 * Run this file to execute all migrations
 */

require_once __DIR__ . '/../bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "Running migrations...\n\n";

$migrationsPath = __DIR__ . '/migrations';
$migrations = glob($migrationsPath . '/*.php');

sort($migrations);

foreach ($migrations as $migration) {
    $migrationName = basename($migration);
    echo "Running: {$migrationName}... ";
    
    try {
        $migrationClass = require $migration;
        $migrationClass->up();
        echo "✓ Done\n";
    } catch (Exception $e) {
        echo "✗ Failed\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nMigrations completed!\n";
