#!/usr/bin/env php
<?php
/**
 * Migration Test & Diagnostic Tool
 */

echo "WhatsApp Mailbox - Migration Diagnostic\n";
echo str_repeat('=', 50) . "\n\n";

// Check PHP version
echo "1. Checking PHP version...\n";
echo "   PHP Version: " . phpversion() . "\n";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "   ✓ PHP version is compatible\n\n";
} else {
    echo "   ✗ PHP version must be 7.4 or higher\n\n";
    exit(1);
}

// Check if .env exists
echo "2. Checking .env file...\n";
if (file_exists(__DIR__ . '/.env')) {
    echo "   ✓ .env file exists\n\n";
} else {
    echo "   ✗ .env file NOT found!\n";
    echo "   → Copy .env.example to .env and configure it\n\n";
    exit(1);
}

// Check vendor directory
echo "3. Checking Composer dependencies...\n";
if (is_dir(__DIR__ . '/vendor')) {
    echo "   ✓ vendor/ directory exists\n";
} else {
    echo "   ✗ vendor/ directory NOT found!\n";
    echo "   → Run: composer install\n\n";
    exit(1);
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "   ✓ Composer autoloader exists\n\n";
} else {
    echo "   ✗ Composer autoloader NOT found!\n\n";
    exit(1);
}

// Try to load bootstrap
echo "4. Testing bootstrap.php...\n";
try {
    require_once __DIR__ . '/bootstrap.php';
    echo "   ✓ Bootstrap loaded successfully\n\n";
} catch (Exception $e) {
    echo "   ✗ Bootstrap failed to load\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test database connection
echo "5. Testing database connection...\n";
try {
    use Illuminate\Database\Capsule\Manager as Capsule;
    Capsule::connection()->getPdo();
    echo "   ✓ Database connection successful\n";
    echo "   Database: " . env('DB_DATABASE') . "\n";
    echo "   Host: " . env('DB_HOST') . "\n\n";
} catch (Exception $e) {
    echo "   ✗ Database connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   \n   Check your .env database credentials:\n";
    echo "   - DB_HOST=" . env('DB_HOST', 'NOT SET') . "\n";
    echo "   - DB_DATABASE=" . env('DB_DATABASE', 'NOT SET') . "\n";
    echo "   - DB_USERNAME=" . env('DB_USERNAME', 'NOT SET') . "\n\n";
    exit(1);
}

// Check migrations directory
echo "6. Checking migrations...\n";
$migrationsPath = __DIR__ . '/database/migrations';
if (!is_dir($migrationsPath)) {
    echo "   ✗ Migrations directory NOT found: $migrationsPath\n\n";
    exit(1);
}

$migrations = glob($migrationsPath . '/*.php');
if (empty($migrations)) {
    echo "   ✗ No migration files found\n\n";
    exit(1);
}

echo "   ✓ Found " . count($migrations) . " migration file(s)\n";
foreach ($migrations as $migration) {
    echo "     - " . basename($migration) . "\n";
}
echo "\n";

// Check if tables already exist
echo "7. Checking existing tables...\n";
try {
    $tables = Capsule::connection()->select('SHOW TABLES');
    if (empty($tables)) {
        echo "   No tables exist yet (this is normal for first run)\n\n";
    } else {
        echo "   Found " . count($tables) . " existing table(s):\n";
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            echo "     - $tableName\n";
        }
        echo "\n   ⚠ Tables already exist. Migrations might be skipped.\n\n";
    }
} catch (Exception $e) {
    echo "   Could not check tables: " . $e->getMessage() . "\n\n";
}

echo str_repeat('=', 50) . "\n";
echo "✓ All checks passed!\n";
echo "\nYou can now run migrations:\n";
echo "  php database/migrate.php\n\n";
