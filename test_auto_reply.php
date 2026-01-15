<?php
/**
 * Auto-Reply Debug Test
 * This script checks if auto-reply system is configured correctly
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\QuickReply;
use App\Models\Message;
use App\Models\Contact;

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Auto-Reply Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #25D366; padding-bottom: 10px; }
        h2 { color: #25D366; margin-top: 30px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #25D366; color: white; }
        tr:hover { background: #f5f5f5; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-active { background: #28a745; color: white; }
        .badge-inactive { background: #dc3545; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Auto-Reply System Diagnostic</h1>
    
    <?php
    // 1. Check Quick Replies
    echo "<h2>1. Quick Replies Status</h2>";
    $quickReplies = QuickReply::all();
    
    if ($quickReplies->isEmpty()) {
        echo "<div class='status error'>❌ <strong>No Quick Replies Found!</strong><br>";
        echo "You need to create quick replies first. Go to: <a href='quick-replies.php'>Quick Replies Page</a></div>";
        echo "<div class='info status'><strong>How to fix:</strong><br>";
        echo "1. Go to Quick Replies page<br>";
        echo "2. Click 'Add Quick Reply'<br>";
        echo "3. Create a reply (e.g., Shortcut: 'hello', Message: 'Hi there!')<br>";
        echo "4. Make sure 'Active' is checked</div>";
    } else {
        echo "<div class='status success'>✅ Found " . $quickReplies->count() . " quick reply(ies)</div>";
        
        echo "<table>";
        echo "<tr><th>Title</th><th>Shortcut</th><th>Status</th><th>Usage Count</th><th>Message Preview</th></tr>";
        foreach ($quickReplies as $qr) {
            $statusBadge = $qr->is_active ? 
                "<span class='badge badge-active'>Active</span>" : 
                "<span class='badge badge-inactive'>Inactive</span>";
            
            echo "<tr>";
            echo "<td>{$qr->title}</td>";
            echo "<td><code>{$qr->shortcut}</code></td>";
            echo "<td>{$statusBadge}</td>";
            echo "<td>{$qr->usage_count}</td>";
            echo "<td>" . substr($qr->message, 0, 50) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        $activeCount = QuickReply::where('is_active', true)->count();
        if ($activeCount === 0) {
            echo "<div class='status error'>❌ <strong>No ACTIVE Quick Replies!</strong><br>";
            echo "All quick replies are disabled. Please activate at least one.</div>";
        } else {
            echo "<div class='status success'>✅ {$activeCount} active quick reply(ies) ready to trigger</div>";
        }
    }
    
    // 2. Check Recent Incoming Messages
    echo "<h2>2. Recent Incoming Messages (Last 10)</h2>";
    $recentMessages = Message::where('direction', 'incoming')
        ->orderBy('timestamp', 'desc')
        ->limit(10)
        ->get();
    
    if ($recentMessages->isEmpty()) {
        echo "<div class='status warning'>⚠️ No incoming messages found.<br>";
        echo "Send a test message to your WhatsApp Business number to verify webhook is working.</div>";
    } else {
        echo "<table>";
        echo "<tr><th>Time</th><th>Contact</th><th>Message</th><th>Type</th></tr>";
        foreach ($recentMessages as $msg) {
            $contact = Contact::find($msg->contact_id);
            echo "<tr>";
            echo "<td>" . date('Y-m-d H:i:s', strtotime($msg->timestamp)) . "</td>";
            echo "<td>" . ($contact ? $contact->name : 'Unknown') . "</td>";
            echo "<td>" . htmlspecialchars(substr($msg->message_body, 0, 50)) . "</td>";
            echo "<td>{$msg->message_type}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Check Auto-Reply Sent
    echo "<h2>3. Auto-Reply Messages Sent (Last 10)</h2>";
    $autoReplies = Message::where('direction', 'outgoing')
        ->where('message_body', 'like', '%[AUTO-REPLY]%')
        ->orderBy('timestamp', 'desc')
        ->limit(10)
        ->get();
    
    if ($autoReplies->isEmpty()) {
        echo "<div class='status warning'>⚠️ No auto-replies sent yet.<br>";
        echo "This could mean:<br>";
        echo "• No incoming messages matched any quick reply shortcuts<br>";
        echo "• Quick replies are not active<br>";
        echo "• Rate limiting prevented sending (max 1 per 30 seconds per contact)</div>";
    } else {
        echo "<div class='status success'>✅ Found " . $autoReplies->count() . " auto-reply message(s)</div>";
        echo "<table>";
        echo "<tr><th>Time</th><th>Contact</th><th>Message</th></tr>";
        foreach ($autoReplies as $msg) {
            $contact = Contact::find($msg->contact_id);
            echo "<tr>";
            echo "<td>" . date('Y-m-d H:i:s', strtotime($msg->timestamp)) . "</td>";
            echo "<td>" . ($contact ? $contact->name : 'Unknown') . "</td>";
            echo "<td>" . htmlspecialchars($msg->message_body) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Environment Check
    echo "<h2>4. Environment Configuration</h2>";
    $apiToken = env('API_ACCESS_TOKEN') ?: env('WHATSAPP_ACCESS_TOKEN');
    $phoneId = env('API_PHONE_NUMBER_ID') ?: env('WHATSAPP_PHONE_NUMBER_ID');
    
    echo "<table>";
    echo "<tr><td><strong>API Access Token</strong></td><td>" . ($apiToken ? "✅ Configured (***" . substr($apiToken, -8) . ")" : "❌ Missing") . "</td></tr>";
    echo "<tr><td><strong>Phone Number ID</strong></td><td>" . ($phoneId ? "✅ Configured ({$phoneId})" : "❌ Missing") . "</td></tr>";
    echo "<tr><td><strong>Webhook URL</strong></td><td><code>" . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . '/webhook.php' : 'Unknown') . "</code></td></tr>";
    echo "</table>";
    
    // 5. How to Test
    echo "<h2>5. How to Test Auto-Reply</h2>";
    echo "<div class='info status'>";
    echo "<strong>Steps to test:</strong><br><br>";
    echo "1. Make sure you have at least one ACTIVE quick reply<br>";
    echo "2. Send a message from WhatsApp that matches the shortcut<br>";
    echo "   • Example: If shortcut is 'hello', send 'hello' from WhatsApp<br>";
    echo "3. Wait a few seconds and check if auto-reply is sent<br>";
    echo "4. Check logs: <code>storage/logs/app.log</code> for [QUICK_REPLY] entries<br><br>";
    echo "<strong>Common Issues:</strong><br>";
    echo "• <strong>Shortcut doesn't match:</strong> Make sure you type the exact shortcut (case-insensitive)<br>";
    echo "• <strong>Quick reply is inactive:</strong> Check the status in Quick Replies page<br>";
    echo "• <strong>Rate limiting:</strong> Can only send 1 auto-reply per 30 seconds per contact<br>";
    echo "• <strong>Webhook not receiving:</strong> Check webhook configuration in Meta Developer Console<br>";
    echo "</div>";
    
    // 6. Check Logs
    echo "<h2>6. Recent Log Entries</h2>";
    $logFile = __DIR__ . '/storage/logs/app.log';
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        $logLines = explode("\n", $logContent);
        $recentLogs = array_slice(array_reverse($logLines), 0, 20);
        
        echo "<div class='code'>";
        foreach ($recentLogs as $line) {
            if (stripos($line, 'QUICK_REPLY') !== false || stripos($line, 'AUTO-REPLY') !== false) {
                echo "<div style='color: #25D366;'>" . htmlspecialchars($line) . "</div>";
            } elseif (stripos($line, 'error') !== false) {
                echo "<div style='color: #dc3545;'>" . htmlspecialchars($line) . "</div>";
            } else {
                echo htmlspecialchars($line) . "<br>";
            }
        }
        echo "</div>";
    } else {
        echo "<div class='status warning'>⚠️ Log file not found: {$logFile}</div>";
    }
    ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 4px;">
        <strong>Need Help?</strong><br>
        • <a href="quick-replies.php">Manage Quick Replies</a><br>
        • <a href="index.php">Back to Mailbox</a><br>
        • Check webhook logs in Meta Developer Console
    </div>
</div>
</body>
</html>
