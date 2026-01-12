<?php
/**
 * Migration: Create migrations tracking table
 */

require_once __DIR__ . '/../bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

echo "Creating migrations tracking table...\n";

try {
    // Create migrations table if it doesn't exist
    if (!DB::schema()->hasTable('migrations')) {
        DB::schema()->create('migrations', function ($table) {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('created_at')->useCurrent();
        });
        echo "✅ Migrations table created!\n";
    } else {
        echo "ℹ️  Migrations table already exists\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
