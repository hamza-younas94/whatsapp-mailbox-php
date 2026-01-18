<?php
/**
 * Force run migration 014 even if it's marked as done
 * This will check for columns and add missing ones
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "🔄 Force running migration 014_enhance_quick_replies.php...\n\n";

try {
    // Load and run the migration directly
    $migrationPath = __DIR__ . '/migrations/014_enhance_quick_replies.php';
    
    if (!file_exists($migrationPath)) {
        echo "❌ Migration file not found: {$migrationPath}\n";
        exit(1);
    }
    
    echo "📁 Loading migration file...\n";
    $migration = require $migrationPath;
    
    if (is_array($migration) && isset($migration['up'])) {
        echo "⚙️  Executing migration 'up' function...\n\n";
        $migration['up']();
        
        echo "\n✅ Migration executed successfully!\n";
        echo "📋 All missing columns should now be added.\n";
    } else {
        echo "❌ Invalid migration format. Migration must return array with 'up' key.\n";
        exit(1);
    }
    
    // Check if migration is recorded
    $isRecorded = Capsule::table('migrations')
        ->where('migration', '014_enhance_quick_replies.php')
        ->exists();
    
    if (!$isRecorded) {
        echo "\n📝 Recording migration in migrations table...\n";
        $batch = Capsule::table('migrations')->max('batch') ?? 0;
        Capsule::table('migrations')->insert([
            'migration' => '014_enhance_quick_replies.php',
            'batch' => $batch + 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Migration recorded!\n";
    } else {
        echo "\n✅ Migration was already recorded in migrations table.\n";
    }
    
    // Verify columns were added
    echo "\n🔍 Verifying columns...\n";
    $schema = Capsule::schema();
    $columns = [
        'priority' => 'integer',
        'business_hours_start' => 'time',
        'shortcuts' => 'json',
        'conditions' => 'json',
        'use_regex' => 'boolean',
        'delay_seconds' => 'integer',
        'media_url' => 'string',
        'excluded_contact_ids' => 'json',
        'sequence_messages' => 'json',
        'success_count' => 'integer',
        'allow_groups' => 'boolean'
    ];
    
    $missing = [];
    foreach ($columns as $column => $type) {
        if ($schema->hasColumn('quick_replies', $column)) {
            echo "   ✅ {$column} exists\n";
        } else {
            echo "   ❌ {$column} MISSING\n";
            $missing[] = $column;
        }
    }
    
    if (empty($missing)) {
        echo "\n🎉 All columns are present! Migration successful.\n";
    } else {
        echo "\n⚠️  Missing columns: " . implode(', ', $missing) . "\n";
        echo "   You may need to run the migration again or check for errors.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

