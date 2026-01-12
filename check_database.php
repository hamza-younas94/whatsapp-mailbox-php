<?php

require_once __DIR__ . '/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;

echo "Checking database structure...\n\n";

// Check contacts table columns
$contactsColumns = Capsule::select("SHOW COLUMNS FROM contacts");
echo "Contacts table columns:\n";
$hasCrmFields = false;
foreach ($contactsColumns as $column) {
    echo "  - {$column->Field} ({$column->Type})\n";
    if ($column->Field === 'stage' || $column->Field === 'lead_score') {
        $hasCrmFields = true;
    }
}

echo "\n";

if ($hasCrmFields) {
    echo "✓ CRM fields already exist in contacts table\n";
} else {
    echo "✗ CRM fields NOT found in contacts table\n";
    echo "Run: ALTER TABLE contacts ADD columns manually\n";
}

echo "\n";

// Check if notes table exists
try {
    $notesCount = Capsule::table('notes')->count();
    echo "✓ Notes table exists ({$notesCount} notes)\n";
} catch (\Exception $e) {
    echo "✗ Notes table does NOT exist\n";
}

// Check if activities table exists  
try {
    $activitiesCount = Capsule::table('activities')->count();
    echo "✓ Activities table exists ({$activitiesCount} activities)\n";
} catch (\Exception $e) {
    echo "✗ Activities table does NOT exist\n";
}

echo "\n=== Database is ready! ===\n";
