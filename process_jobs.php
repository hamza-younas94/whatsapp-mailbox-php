<?php
/**
 * Background Job Processor
 * Run this as a cron job every minute: * * * * * php /path/to/process_jobs.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\ScheduledMessage;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use GuzzleHttp\Client;

echo "[" . date('Y-m-d H:i:s') . "] Job processor started\n";

// WhatsApp API configuration
$phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
$accessToken = getenv('WHATSAPP_ACCESS_TOKEN');

if (!$phoneNumberId || !$accessToken) {
    echo "⚠️  WhatsApp credentials not configured - skipping job processing\n";
    echo "   To enable: Set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN in .env\n";
    echo "[" . date('Y-m-d H:i:s') . "] Job processor completed (skipped)\n\n";
    exit(0); // Exit gracefully without error
}

$client = new Client([
    'base_uri' => 'https://graph.facebook.com/v24.0/',
    'timeout' => 30,
]);

// Process scheduled messages
processScheduledMessages($client, $phoneNumberId, $accessToken);

// Process broadcasts
processBroadcasts($client, $phoneNumberId, $accessToken);

echo "[" . date('Y-m-d H:i:s') . "] Job processor completed\n\n";

/**
 * Process scheduled messages that are due
 */
function processScheduledMessages($client, $phoneNumberId, $accessToken) {
    $dueMessages = ScheduledMessage::with('contact')
        ->where('status', 'pending')
        ->where('scheduled_at', '<=', now())
        ->limit(50) // Process 50 at a time
        ->get();
    
    if ($dueMessages->isEmpty()) {
        echo "No scheduled messages due\n";
        return;
    }
    
    echo "Processing " . $dueMessages->count() . " scheduled messages\n";
    
    foreach ($dueMessages as $msg) {
        try {
            $response = $client->post("{$phoneNumberId}/messages", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $msg->contact->phone_number,
                    'type' => 'text',
                    'text' => ['body' => $msg->message]
                ]
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $msg->update([
                'status' => 'sent',
                'sent_at' => now(),
                'whatsapp_message_id' => $result['messages'][0]['id'] ?? null
            ]);
            
            echo "  ✅ Sent to {$msg->contact->phone_number}\n";
            
            // If recurring, schedule next occurrence
            if ($msg->is_recurring && $msg->recurrence_pattern) {
                scheduleNextRecurrence($msg);
            }
            
        } catch (Exception $e) {
            $msg->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            echo "  ❌ Failed to {$msg->contact->phone_number}: " . $e->getMessage() . "\n";
        }
        
        usleep(500000); // 500ms delay between messages
    }
}

/**
 * Process broadcasts that are in sending status
 */
function processBroadcasts($client, $phoneNumberId, $accessToken) {
    // Start broadcasts that are scheduled
    $scheduledBroadcasts = Broadcast::where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->get();
    
    foreach ($scheduledBroadcasts as $broadcast) {
        $broadcast->update([
            'status' => 'sending',
            'started_at' => now()
        ]);
        echo "Started broadcast: {$broadcast->name}\n";
    }
    
    // Process broadcasts that are sending
    $sendingBroadcasts = Broadcast::where('status', 'sending')->get();
    
    if ($sendingBroadcasts->isEmpty()) {
        echo "No broadcasts to process\n";
        return;
    }
    
    foreach ($sendingBroadcasts as $broadcast) {
        echo "Processing broadcast: {$broadcast->name}\n";
        
        // Get pending recipients (batch of 50)
        $pendingRecipients = BroadcastRecipient::with('contact')
            ->where('broadcast_id', $broadcast->id)
            ->where('status', 'pending')
            ->limit(50)
            ->get();
        
        if ($pendingRecipients->isEmpty()) {
            // All sent, mark as completed
            $broadcast->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            echo "  ✅ Broadcast completed!\n";
            continue;
        }
        
        foreach ($pendingRecipients as $recipient) {
            try {
                $response = $client->post("{$phoneNumberId}/messages", [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'messaging_product' => 'whatsapp',
                        'to' => $recipient->contact->phone_number,
                        'type' => 'text',
                        'text' => ['body' => $broadcast->message]
                    ]
                ]);
                
                $result = json_decode($response->getBody(), true);
                
                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'whatsapp_message_id' => $result['messages'][0]['id'] ?? null
                ]);
                
                $broadcast->increment('sent_count');
                
                echo "  ✅ Sent to {$recipient->contact->phone_number}\n";
                
            } catch (Exception $e) {
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
                
                $broadcast->increment('failed_count');
                
                echo "  ❌ Failed to {$recipient->contact->phone_number}: " . $e->getMessage() . "\n";
            }
            
            usleep(500000); // 500ms delay between messages
        }
    }
}

/**
 * Schedule next recurrence for recurring messages
 */
function scheduleNextRecurrence($originalMsg) {
    $nextScheduledAt = null;
    
    switch ($originalMsg->recurrence_pattern) {
        case 'daily':
            $nextScheduledAt = $originalMsg->scheduled_at->addDay();
            break;
        case 'weekly':
            $nextScheduledAt = $originalMsg->scheduled_at->addWeek();
            break;
        case 'monthly':
            $nextScheduledAt = $originalMsg->scheduled_at->addMonth();
            break;
    }
    
    if ($nextScheduledAt) {
        ScheduledMessage::create([
            'contact_id' => $originalMsg->contact_id,
            'message' => $originalMsg->message,
            'message_type' => $originalMsg->message_type,
            'template_name' => $originalMsg->template_name,
            'scheduled_at' => $nextScheduledAt,
            'status' => 'pending',
            'is_recurring' => true,
            'recurrence_pattern' => $originalMsg->recurrence_pattern,
            'created_by' => $originalMsg->created_by
        ]);
        
        echo "  🔄 Scheduled next recurrence for {$nextScheduledAt}\n";
    }
}
