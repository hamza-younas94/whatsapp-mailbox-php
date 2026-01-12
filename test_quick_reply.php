<?php
/**
 * Test Quick Reply Functionality
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\QuickReply;

echo "=== Quick Reply Test ===\n\n";

// Check if any quick replies exist
$quickReplies = QuickReply::where('is_active', true)->get();

echo "Active Quick Replies: " . $quickReplies->count() . "\n\n";

if ($quickReplies->count() === 0) {
    echo "❌ No active quick replies found!\n";
    echo "Please create a quick reply first via the UI.\n\n";
    
    echo "Creating sample quick reply...\n";
    $sample = QuickReply::create([
        'shortcut' => '/hello',
        'title' => 'Welcome Message',
        'message' => "Hello! Thank you for contacting us. How can I help you today?",
        'is_active' => true,
        'created_by' => 1
    ]);
    echo "✅ Created sample quick reply: {$sample->shortcut}\n\n";
    
    $quickReplies = QuickReply::where('is_active', true)->get();
}

// List all quick replies
echo "Quick Replies:\n";
foreach ($quickReplies as $qr) {
    echo "  {$qr->shortcut} - {$qr->title} (Used {$qr->usage_count} times)\n";
}
echo "\n";

// Test matching logic
$testMessages = ['hello', '/hello', 'HELLO', '/HELLO', 'hours', '/hours'];

echo "Testing matching logic:\n";
foreach ($testMessages as $msg) {
    $searchText = strtolower(trim($msg));
    if (strpos($searchText, '/') === 0) {
        $shortcut = $searchText;
    } else {
        $shortcut = '/' . $searchText;
    }
    
    $found = QuickReply::where('shortcut', $shortcut)
        ->where('is_active', true)
        ->first();
    
    if ($found) {
        echo "  ✅ '{$msg}' → matched '{$shortcut}' → {$found->title}\n";
    } else {
        echo "  ❌ '{$msg}' → searched '{$shortcut}' → NO MATCH\n";
    }
}

echo "\n=== Test Complete ===\n";
