<?php
/**
 * Automatic Migration Runner
 * This script runs all pending migrations automatically
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

class MigrationRunner
{
    private $migrationsPath;
    
    public function __construct()
    {
        $this->migrationsPath = __DIR__ . '/migrations';
    }
    
    /**
     * Run all pending migrations
     */
    public function run($silent = false)
    {
        if (!$silent) {
            echo "🔄 Running database migrations...\n\n";
        }
        
        // Ensure migrations table exists
        $this->ensureMigrationsTable();
        
        // Get all migration files
        $migrationFiles = $this->getMigrationFiles();
        
        // Get already run migrations
        $ranMigrations = $this->getRanMigrations();
        
        // Find pending migrations
        $pendingMigrations = array_diff($migrationFiles, $ranMigrations);
        
        if (empty($pendingMigrations)) {
            if (!$silent) {
                echo "✅ No pending migrations. Database is up to date!\n";
            }
            return;
        }
        
        // Get next batch number
        $batch = $this->getNextBatchNumber();
        
        // Run each pending migration
        foreach ($pendingMigrations as $migration) {
            try {
                if (!$silent) {
                    echo "⚙️  Running: {$migration}...\n";
                }
                
                // Load migration file (returns array with 'up' and 'down' functions)
                $migrationData = require $this->migrationsPath . '/' . $migration;
                
                // Handle both array format and object format
                if (is_array($migrationData) && isset($migrationData['up'])) {
                    // Migration returns array: ['up' => function(), 'down' => function()]
                    $migrationData['up']();
                } elseif (is_object($migrationData) && method_exists($migrationData, 'up')) {
                    // Migration returns object with up() method
                    $migrationData->up();
                } else {
                    throw new Exception("Invalid migration format. Migration must return array with 'up' key or object with up() method.");
                }
                
                // Record migration as completed
                DB::table('migrations')->insert([
                    'migration' => $migration,
                    'batch' => $batch,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if (!$silent) {
                    echo "   ✅ Success!\n";
                }
            } catch (Exception $e) {
                if (!$silent) {
                    echo "   ❌ Error: " . $e->getMessage() . "\n";
                    echo "   ⚠️  Migration stopped at: {$migration}\n\n";
                }
                break;
            }
        }
        
        if (!$silent) {
            echo "\n✅ All migrations completed successfully!\n";
        }
    }
    
    /**
     * Ensure migrations tracking table exists
     */
    private function ensureMigrationsTable()
    {
        if (!DB::schema()->hasTable('migrations')) {
            DB::schema()->create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }
    
    /**
     * Get all migration files
     */
    private function getMigrationFiles()
    {
        $files = scandir($this->migrationsPath);
        $migrations = [];
        
        foreach ($files as $file) {
            if (preg_match('/^\d+_.*\.php$/', $file)) {
                $migrations[] = $file;
            }
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Get migrations that have already been run
     */
    private function getRanMigrations()
    {
        try {
            return DB::table('migrations')->pluck('migration')->toArray();
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatchNumber()
    {
        try {
            $lastBatch = DB::table('migrations')->max('batch');
            return $lastBatch ? $lastBatch + 1 : 1;
        } catch (Exception $e) {
            return 1;
        }
    }
}

// Run migrations if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $runner = new MigrationRunner();
    $runner->run();
}
