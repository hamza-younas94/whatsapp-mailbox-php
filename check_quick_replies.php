<?php
/**
 * Check Quick Replies Database Status
 * Run this to verify the actual database values
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\QuickReply;

echo "\n=== QUICK REPLIES DATABASE CHECK ===\n\n";

// Get ALL quick replies (no filters)
$allReplies = QuickReply::all();
echo "Total Quick Replies in Database: " . $allReplies->count() . "\n\n";

if ($allReplies->isEmpty()) {
    echo "❌ No quick replies found in database!\n";
    exit;
}

echo "ID | Shortcut | Title | Active | Created\n";
echo str_repeat("-", 80) . "\n";

foreach ($allReplies as $qr) {
    $active = $qr->is_active ? 'YES' : 'NO';
    $activeValue = var_export($qr->is_active, true);
    echo sprintf(
        "%d | %-10s | %-20s | %-6s (%s) | %s\n",
        $qr->id,
        $qr->shortcut,
        substr($qr->title, 0, 20),
        $active,
        $activeValue,
        $qr->created_at->format('Y-m-d H:i')
    );
}

echo "\n--- Checking is_active column type ---\n";

// Check column information
try {
    $db = \Illuminate\Database\Capsule\Manager::connection();
    $columns = $db->select("SHOW COLUMNS FROM quick_replies WHERE Field = 'is_active'");
    
    if (!empty($columns)) {
        $col = $columns[0];
        echo "Column Type: " . $col->Type . "\n";
        echo "Null: " . $col->Null . "\n";
        echo "Default: " . ($col->Default ?? 'NULL') . "\n";
    }
} catch (\Exception $e) {
    echo "Error checking column: " . $e->getMessage() . "\n";
}

echo "\n--- Testing Query Filters ---\n";

// Test the exact query used in auto-reply
$activeReplies = QuickReply::where('is_active', true)->get();
echo "where('is_active', true): " . $activeReplies->count() . " results\n";

$activeReplies2 = QuickReply::where('is_active', 1)->get();
echo "where('is_active', 1): " . $activeReplies2->count() . " results\n";

$activeReplies3 = QuickReply::where('is_active', '=', true)->get();
echo "where('is_active', '=', true): " . $activeReplies3->count() . " results\n";

$activeReplies4 = QuickReply::where('is_active', '=', 1)->get();
echo "where('is_active', '=', 1): " . $activeReplies4->count() . " results\n";

echo "\n--- Raw Values Check ---\n";
foreach ($allReplies as $qr) {
    $rawValue = $db->table('quick_replies')->where('id', $qr->id)->value('is_active');
    echo "ID {$qr->id}: is_active = " . var_export($rawValue, true) . " (type: " . gettype($rawValue) . ")\n";
}

echo "\n✅ Check complete!\n\n";
