<?php
/**
 * Mark existing tables as migrated
 * Run this ONCE to tell the system that old migrations are already done
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

echo "🔄 Marking existing migrations as completed...\n\n";

// Ensure migrations table exists
if (!DB::schema()->hasTable('migrations')) {
    DB::schema()->create('migrations', function ($table) {
        $table->id();
        $table->string('migration');
        $table->integer('batch');
        $table->timestamp('created_at')->useCurrent();
    });
    echo "✅ Created migrations tracking table\n";
}

// List of old migrations that are already done
$completedMigrations = [
    '001_create_contacts_table.php',
    '002_create_messages_table.php',
    '003_create_admin_users_table.php',
    '004_create_config_table.php',
    '005_add_crm_fields_to_contacts.php',
    '006_create_notes_table.php',
    '007_create_activities_table.php',
];

$batch = 0; // Mark as batch 0 (historical)

foreach ($completedMigrations as $migration) {
    // Check if already recorded
    $exists = DB::table('migrations')->where('migration', $migration)->exists();
    
    if (!$exists) {
        DB::table('migrations')->insert([
            'migration' => $migration,
            'batch' => $batch,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Marked as completed: {$migration}\n";
    } else {
        echo "ℹ️  Already marked: {$migration}\n";
    }
}

echo "\n✅ All existing migrations marked as completed!\n";
echo "From now on, only run: php migrate.php\n";
echo "Or just refresh your browser (migrations run automatically)\n";
