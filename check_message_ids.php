<?php
/**
 * Check and report on messages missing message_id
 * These could cause reactions to not display properly
 */

require 'bootstrap.php';

use App\Models\Message;

// Check messages without message_id
$messagesWithoutId = Message::whereNull('message_id')
    ->orWhere('message_id', '')
    ->count();

echo "Messages without message_id: " . $messagesWithoutId . "\n";

// Get all reactions and check if their parents exist
$reactions = Message::where('message_type', 'reaction')->get();
echo "Total reactions: " . count($reactions) . "\n";

$orphaned = 0;
$linked = 0;

foreach ($reactions as $reaction) {
    $parent = Message::where('message_id', $reaction->parent_message_id)
        ->first();
    
    if (!$parent) {
        $orphaned++;
        if ($orphaned <= 3) {
            echo "ORPHANED: Reaction ID {$reaction->id}, parent_message_id: {$reaction->parent_message_id}, body: {$reaction->message_body}\n";
        }
    } else {
        $linked++;
    }
}

echo "\nReaction Status:\n";
echo "  Linked to parent: " . $linked . "\n";
echo "  Orphaned (no parent): " . $orphaned . "\n";

if ($orphaned > 0) {
    echo "\nTo debug, check if the parent messages have their message_id field set:\n";
    $sampleReaction = Message::where('message_type', 'reaction')->first();
    if ($sampleReaction) {
        $parentMessage = Message::find($sampleReaction->parent_message_id);
        echo "  Sample reaction parent_message_id: {$sampleReaction->parent_message_id}\n";
        echo "  Message with ID=$sampleReaction->parent_message_id exists: " . ($parentMessage ? "YES" : "NO") . "\n";
    }
}

echo "\nDone.\n";
