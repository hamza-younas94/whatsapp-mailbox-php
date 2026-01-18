<?php
/**
 * Manual migration runner for 014_enhance_quick_replies.php
 * Run this if the automatic migration didn't work
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "🔄 Running migration 014_enhance_quick_replies.php manually...\n\n";

try {
    // Check if migration has already been run
    $alreadyRun = Capsule::table('migrations')
        ->where('migration', '014_enhance_quick_replies.php')
        ->exists();
    
    if ($alreadyRun) {
        echo "⚠️  Migration 014_enhance_quick_replies.php has already been recorded as run.\n";
        echo "   If columns are missing, you may need to check manually.\n";
        exit(1);
    }
    
    // Load and run the migration
    $migrationPath = __DIR__ . '/migrations/014_enhance_quick_replies.php';
    
    if (!file_exists($migrationPath)) {
        echo "❌ Migration file not found: {$migrationPath}\n";
        exit(1);
    }
    
    $migration = require $migrationPath;
    
    if (is_array($migration) && isset($migration['up'])) {
        echo "⚙️  Executing migration...\n";
        $migration['up']();
        
        // Record migration as completed
        $batch = Capsule::table('migrations')->max('batch') ?? 0;
        Capsule::table('migrations')->insert([
            'migration' => '014_enhance_quick_replies.php',
            'batch' => $batch + 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "✅ Migration completed and recorded successfully!\n";
    } else {
        echo "❌ Invalid migration format\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

