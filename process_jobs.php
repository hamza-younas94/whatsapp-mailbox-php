<?php
/**
 * Background Job Processor
 * Run this as a cron job every minute: * * * * * php /path/to/process_jobs.php
 */

// Prevent session from starting in CLI mode
define('NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';

use App\Models\ScheduledMessage;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\DripSubscriber;
use App\Services\WhatsAppService;
use GuzzleHttp\Client;

echo "[" . date('Y-m-d H:i:s') . "] Job processor started\n";

// WhatsApp API configuration
$phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
$accessToken = env('WHATSAPP_ACCESS_TOKEN');

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

// Process drip campaigns
processDripCampaigns($phoneNumberId, $accessToken);

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
        // Check upcoming messages for better logging
        $upcomingCount = ScheduledMessage::where('status', 'pending')
            ->where('scheduled_at', '>', now())
            ->count();
        
        if ($upcomingCount > 0) {
            $nextMessage = ScheduledMessage::where('status', 'pending')
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at', 'asc')
                ->first();
            echo "No scheduled messages due. Next message at: {$nextMessage->scheduled_at} ({$upcomingCount} pending)\n";
        } else {
            echo "No scheduled messages due\n";
        }
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
            $whatsappMessageId = $result['messages'][0]['id'] ?? null;
            
            // Mark scheduled message as sent
            $msg->update([
                'status' => 'sent',
                'sent_at' => now(),
                'whatsapp_message_id' => $whatsappMessageId
            ]);
            
            // Try to save to message history (don't fail if this errors)
            try {
                \App\Models\Message::updateOrCreate(
                    ['message_id' => $whatsappMessageId],
                    [
                        'user_id' => $msg->contact->user_id,  // MULTI-TENANT: add user_id
                        'contact_id' => $msg->contact->id,
                        'phone_number' => $msg->contact->phone_number,
                        'direction' => 'outgoing',
                        'message_type' => 'text',
                        'message_body' => $msg->message,
                        'timestamp' => now(),
                        'is_read' => true
                    ]
                );
                echo "  ✅ Sent to {$msg->contact->phone_number} (saved to history)\n";
            } catch (\Exception $e) {
                echo "  ✅ Sent to {$msg->contact->phone_number} (history save failed: {$e->getMessage()})\n";
            }
            
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
                
                $whatsappMessageId = $result['messages'][0]['id'] ?? null;
                
                // Mark broadcast recipient as sent
                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'whatsapp_message_id' => $whatsappMessageId
                ]);
                
                $broadcast->increment('sent_count');
                
                // Try to save to message history (don't fail if this errors)
                try {
                    \App\Models\Message::updateOrCreate(
                        ['message_id' => $whatsappMessageId],
                        [
                            'user_id' => $recipient->contact->user_id,  // MULTI-TENANT: add user_id
                            'contact_id' => $recipient->contact->id,
                            'phone_number' => $recipient->contact->phone_number,
                            'direction' => 'outgoing',
                            'message_type' => 'text',
                            'message_body' => $broadcast->message,
                            'timestamp' => now(),
                            'is_read' => true
                        ]
                    );
                    echo "  ✅ Sent to {$recipient->contact->phone_number} (saved to history)\n";
                } catch (\Exception $e) {
                    echo "  ✅ Sent to {$recipient->contact->phone_number} (history save failed: {$e->getMessage()})\n";
                }
                
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
            'user_id' => $originalMsg->user_id,  // MULTI-TENANT: add user_id
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

/**
 * Process drip campaigns - send next steps to subscribers
 */
function processDripCampaigns($phoneNumberId, $accessToken) {
    $whatsappService = new WhatsAppService();
    
    // Get subscribers whose next_send_at is due
    $dueSubscribers = DripSubscriber::with(['campaign', 'contact'])
        ->where('status', 'active')
        ->where('next_send_at', '<=', now())
        ->limit(50) // Process 50 at a time
        ->get();
    
    if ($dueSubscribers->isEmpty()) {
        // Check upcoming sends for better logging
        $upcomingCount = DripSubscriber::where('status', 'active')
            ->where('next_send_at', '>', now())
            ->count();
        
        if ($upcomingCount > 0) {
            $nextSend = DripSubscriber::where('status', 'active')
                ->where('next_send_at', '>', now())
                ->orderBy('next_send_at', 'asc')
                ->first();
            echo "No drip campaign steps due. Next step at: {$nextSend->next_send_at} ({$upcomingCount} pending)\n";
        } else {
            echo "No drip campaign steps due\n";
        }
        return;
    }
    
    echo "Processing " . $dueSubscribers->count() . " drip campaign steps\n";
    
    foreach ($dueSubscribers as $subscriber) {
        try {
            $result = $whatsappService->sendDripCampaignStep($subscriber);
            
            if ($result['success']) {
                if (isset($result['completed']) && $result['completed']) {
                    echo "  ✅ Campaign completed for {$subscriber->contact->phone_number}\n";
                } else {
                    echo "  ✅ Sent step to {$subscriber->contact->phone_number}. Next: {$result['next_send_at']}\n";
                }
            } else {
                echo "  ❌ Failed to send step to {$subscriber->contact->phone_number}: " . ($result['error'] ?? 'Unknown error') . "\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Error processing subscriber {$subscriber->id}: " . $e->getMessage() . "\n";
        }
        
        usleep(500000); // 500ms delay between messages
    }
}
