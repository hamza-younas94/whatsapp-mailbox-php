<?php
/**
 * Initialize Config Values
 * Run this if migration hasn't executed properly
 */

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "Initializing config values...\n";

try {
    // Check and insert message_limit
    $limitExists = Capsule::table('config')
        ->where('config_key', 'message_limit')
        ->exists();
    
    if (!$limitExists) {
        Capsule::table('config')->insert([
            'config_key' => 'message_limit',
            'config_value' => '500',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "✓ Created message_limit config (500)\n";
    } else {
        echo "✓ message_limit already exists\n";
    }
    
    // Check and insert messages_sent_count
    $countExists = Capsule::table('config')
        ->where('config_key', 'messages_sent_count')
        ->exists();
    
    if (!$countExists) {
        Capsule::table('config')->insert([
            'config_key' => 'messages_sent_count',
            'config_value' => '0',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "✓ Created messages_sent_count config (0)\n";
    } else {
        echo "✓ messages_sent_count already exists\n";
    }
    
    // Display current values
    $all = Capsule::table('config')
        ->whereIn('config_key', ['message_limit', 'messages_sent_count'])
        ->get();
    
    echo "\nCurrent config values:\n";
    foreach ($all as $config) {
        echo "  - {$config->config_key}: {$config->config_value}\n";
    }
    
    echo "\n✓ Config initialization complete!\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
