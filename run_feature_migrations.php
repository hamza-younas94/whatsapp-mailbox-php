<?php
/**
 * Run All New Feature Migrations
 */

require_once __DIR__ . '/config/database.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "🚀 Running all new feature migrations...\n\n";

// Get all migration files
$migrationFiles = glob(__DIR__ . '/migrations/*.php');
sort($migrationFiles);

// Get already run migrations
try {
    $ranMigrations = Capsule::table('migrations')->pluck('migration')->toArray();
} catch (Exception $e) {
    $ranMigrations = [];
}

$newMigrations = 0;
$errors = 0;

foreach ($migrationFiles as $file) {
    $migrationName = basename($file, '.php');
    
    // Skip if already run
    if (in_array($migrationName, $ranMigrations)) {
        echo "⏩ Skipped: $migrationName (already run)\n";
        continue;
    }
    
    try {
        echo "▶️  Running: $migrationName\n";
        
        $migration = require $file;
        
        if (isset($migration['up']) && is_callable($migration['up'])) {
            $migration['up']();
            
            // Record migration
            Capsule::table('migrations')->insert([
                'migration' => $migrationName,
                'batch' => Capsule::table('migrations')->max('batch') + 1
            ]);
            
            echo "✅ Completed: $migrationName\n\n";
            $newMigrations++;
        } else {
            echo "⚠️  Skipped: $migrationName (no 'up' function)\n\n";
        }
    } catch (Exception $e) {
        echo "❌ Error in $migrationName: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Migration Summary:\n";
echo "   ✅ New migrations run: $newMigrations\n";
echo "   ❌ Errors: $errors\n";
echo "   📁 Total migration files: " . count($migrationFiles) . "\n";
echo str_repeat("=", 60) . "\n\n";

if ($newMigrations > 0) {
    echo "🎉 All new features have been set up!\n\n";
    echo "New features added:\n";
    echo "  🏷️  Tags System - Organize contacts\n";
    echo "  ⚡ Quick Replies - Save time with templates\n";
    echo "  ⏰ Scheduled Messages - Send messages later\n";
    echo "  📊 Segments - Smart contact grouping\n";
    echo "  📢 Broadcasts - Send to multiple contacts\n";
    echo "  🔄 Workflows - Automate actions\n\n";
    
    echo "Access these features at:\n";
    echo "  • https://whatsapp.nexofydigital.com/tags.php\n";
    echo "  • https://whatsapp.nexofydigital.com/quick-replies.php\n";
    echo "  • https://whatsapp.nexofydigital.com/broadcasts.php\n";
    echo "  • https://whatsapp.nexofydigital.com/analytics.php\n";
    echo "  • https://whatsapp.nexofydigital.com/segments.php\n";
    echo "  • https://whatsapp.nexofydigital.com/scheduled-messages.php\n\n";
} else {
    echo "ℹ️  All migrations are already up to date!\n\n";
}

if ($errors > 0) {
    echo "⚠️  Some migrations failed. Please check the errors above.\n";
    exit(1);
}

echo "✨ Done!\n";
