<?php
/**
 * Background Job Processor
 * Run this as a cron job every minute: * * * * * php /path/to/process_jobs.php
 */

// This script MUST be run via CLI, not HTTP
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "ERROR: This script must be run via CLI, not HTTP\n\n";
    echo "Correct cron setup:\n";
    echo "* * * * * cd " . __DIR__ . " && /usr/bin/php process_jobs.php >> /path/to/cron.log 2>&1\n\n";
    echo "DO NOT use wget or curl to call this script.\n";
    exit(1);
}

// Prevent session from starting in CLI mode
define('NO_SESSION', true);

require_once __DIR__ . '/bootstrap.php';

use App\Models\ScheduledMessage;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\DripSubscriber;
use App\Services\WhatsAppService;
use App\Services\JobQueue;
use Illuminate\Database\Capsule\Manager as Capsule;
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
enqueueScheduledMessageJobs();
enqueueBroadcastRecipientJobs();
processJobQueue($client, $phoneNumberId, $accessToken);

// Process drip campaigns (existing flow)
processDripCampaigns($phoneNumberId, $accessToken);

// Retry failed webhooks with backoff
processWebhookRetries();

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
 * Enqueue scheduled messages that are due into durable job queue
 */
function enqueueScheduledMessageJobs() {
    $due = ScheduledMessage::with('contact')
        ->where('status', 'pending')
        ->where('scheduled_at', '<=', now())
        ->limit(200)
        ->get();

    foreach ($due as $msg) {
        $userId = $msg->contact->user_id ?? $msg->user_id ?? null;
        if (!$userId) {
            continue;
        }
        JobQueue::enqueue('scheduled_message', $msg->id, [], $userId, $msg->scheduled_at?->format('Y-m-d H:i:s'));
        $msg->update(['status' => 'queued']);
    }
}

/**
 * Enqueue broadcast recipients into durable job queue
 */
function enqueueBroadcastRecipientJobs() {
    // Move scheduled broadcasts to sending when due
    $scheduledBroadcasts = Broadcast::where('status', 'scheduled')
        ->where('scheduled_at', '<=', now())
        ->get();
    foreach ($scheduledBroadcasts as $broadcast) {
        $broadcast->update(['status' => 'sending', 'started_at' => now()]);
    }

    $pendingRecipients = BroadcastRecipient::with(['contact', 'broadcast'])
        ->where('status', 'pending')
        ->limit(500)
        ->get();

    foreach ($pendingRecipients as $recipient) {
        if (!$recipient->broadcast || !$recipient->contact) {
            continue;
        }
        if ($recipient->broadcast->status !== 'sending') {
            $recipient->broadcast->update(['status' => 'sending', 'started_at' => now()]);
        }
        $userId = $recipient->contact->user_id ?? null;
        if (!$userId) {
            continue;
        }
        JobQueue::enqueue('broadcast_recipient', $recipient->id, ['broadcast_id' => $recipient->broadcast_id], $userId);
        $recipient->update(['status' => 'queued']);
    }
}

/**
 * Process durable job queue for scheduled messages and broadcasts
 */
function processJobQueue($client, $phoneNumberId, $accessToken) {
    $processed = 0;
    while (true) {
        $batch = JobQueue::reserveBatch(50);
        if (empty($batch)) {
            break;
        }

        foreach ($batch as $job) {
            $payload = $job->payload ? json_decode($job->payload, true) : [];
            try {
                switch ($job->type) {
                    case 'scheduled_message':
                        handleScheduledMessageJob($job, $client, $phoneNumberId, $accessToken);
                        break;
                    case 'broadcast_recipient':
                        handleBroadcastRecipientJob($job, $client, $phoneNumberId, $accessToken, $payload);
                        break;
                    default:
                        JobQueue::markSucceeded($job->id);
                        break;
                }
                $processed++;
            } catch (\Exception $e) {
                JobQueue::markFailed($job->id, $e->getMessage());
            }
        }
    }
    if ($processed > 0) {
        echo "Processed {$processed} queued jobs\n";
    }
}

function handleScheduledMessageJob($job, $client, $phoneNumberId, $accessToken) {
    $msg = ScheduledMessage::with('contact')->find($job->reference_id);
    if (!$msg || !$msg->contact) {
        JobQueue::markSucceeded($job->id);
        return;
    }
    if ($msg->status === 'sent') {
        JobQueue::markSucceeded($job->id);
        return;
    }
    if ($msg->scheduled_at && $msg->scheduled_at > now()) {
        JobQueue::markFailed($job->id, 'Not due yet', 3, 300);
        return;
    }

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

        $msg->update([
            'status' => 'sent',
            'sent_at' => now(),
            'whatsapp_message_id' => $whatsappMessageId
        ]);

        // Persist to history (best-effort)
        try {
            \App\Models\Message::updateOrCreate(
                ['message_id' => $whatsappMessageId],
                [
                    'user_id' => $msg->contact->user_id,
                    'contact_id' => $msg->contact->id,
                    'phone_number' => $msg->contact->phone_number,
                    'direction' => 'outgoing',
                    'message_type' => 'text',
                    'message_body' => $msg->message,
                    'timestamp' => now(),
                    'is_read' => true
                ]
            );
        } catch (\Exception $e) {
            // ignore
        }

        JobQueue::markSucceeded($job->id);
    } catch (\Exception $e) {
        $msg->update([
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ]);
        JobQueue::markFailed($job->id, $e->getMessage());
    }
}

function handleBroadcastRecipientJob($job, $client, $phoneNumberId, $accessToken, array $payload) {
    $recipient = BroadcastRecipient::with(['contact', 'broadcast'])->find($job->reference_id);
    if (!$recipient || !$recipient->contact || !$recipient->broadcast) {
        JobQueue::markSucceeded($job->id);
        return;
    }
    if ($recipient->status === 'sent') {
        JobQueue::markSucceeded($job->id);
        return;
    }

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
                'text' => ['body' => $recipient->broadcast->message]
            ]
        ]);

        $result = json_decode($response->getBody(), true);
        $whatsappMessageId = $result['messages'][0]['id'] ?? null;

        $recipient->update([
            'status' => 'sent',
            'sent_at' => now(),
            'whatsapp_message_id' => $whatsappMessageId
        ]);
        $recipient->broadcast->increment('sent_count');

        try {
            \App\Models\Message::updateOrCreate(
                ['message_id' => $whatsappMessageId],
                [
                    'user_id' => $recipient->contact->user_id,
                    'contact_id' => $recipient->contact->id,
                    'phone_number' => $recipient->contact->phone_number,
                    'direction' => 'outgoing',
                    'message_type' => 'text',
                    'message_body' => $recipient->broadcast->message,
                    'timestamp' => now(),
                    'is_read' => true
                ]
            );
        } catch (\Exception $e) {
            // ignore
        }

        JobQueue::markSucceeded($job->id);
    } catch (\Exception $e) {
        $recipient->update([
            'status' => 'failed',
            'error_message' => $e->getMessage()
        ]);
        $recipient->broadcast->increment('failed_count');
        JobQueue::markFailed($job->id, $e->getMessage());
    }
}

/**
 * Retry failed webhook deliveries with backoff (best-effort)
 */
function processWebhookRetries() {
    if (!Capsule::schema()->hasTable('webhook_deliveries')) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $hasPayload = Capsule::schema()->hasColumn('webhook_deliveries', 'payload');

    $rows = Capsule::table('webhook_deliveries as wd')
        ->join('webhooks as wh', 'wd.webhook_id', '=', 'wh.id')
        ->where('wh.is_active', true)
        ->where('wd.retry_count', '<', 3)
        ->where(function($q) use ($now) {
            $q->whereNull('wd.next_retry_at')->orWhere('wd.next_retry_at', '<=', $now);
        })
        ->where(function($q){
            $q->where('wd.status', 'failed')->orWhere('wd.status', 'error')->orWhere('wd.status_code', '>=', 400);
        })
        ->limit(50)
        ->get(['wd.*', 'wh.url as webhook_url', 'wh.secret as webhook_secret']);

    if ($rows->isEmpty()) {
        return;
    }

    foreach ($rows as $row) {
        $payload = [];
        if ($hasPayload && isset($row->payload)) {
            $decoded = json_decode($row->payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        // Fallback payload
        if (empty($payload)) {
            $payload = [
                'event' => $row->event ?? 'webhook.retry',
                'delivery_id' => $row->id,
                'timestamp' => date('c')
            ];
        }

        $secret = \App\Services\Encryption::decrypt($row->webhook_secret ?? '');
        $signature = hash_hmac('sha256', json_encode($payload), $secret ?? '');

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post($row->webhook_url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $payload['event'] ?? 'webhook.retry'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            Capsule::table('webhook_deliveries')
                ->where('id', $row->id)
                ->update([
                    'status' => $statusCode >= 200 && $statusCode < 300 ? 'success' : 'failed',
                    'status_code' => $statusCode,
                    'retry_count' => $row->retry_count,
                    'last_error' => null,
                    'next_retry_at' => null,
                    'attempted_at' => date('Y-m-d H:i:s'),
                    'response_body' => method_exists($response, 'getBody') ? (string) $response->getBody() : null,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } catch (\Exception $e) {
            $retryCount = $row->retry_count + 1;
            $backoff = min(pow(2, $retryCount) * 60, 3600); // max 1h
            Capsule::table('webhook_deliveries')
                ->where('id', $row->id)
                ->update([
                    'status' => 'failed',
                    'retry_count' => $retryCount,
                    'last_error' => $e->getMessage(),
                    'next_retry_at' => date('Y-m-d H:i:s', time() + $backoff),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
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
    // Cache per-user WhatsAppService to avoid repeated init
    $serviceCache = [];

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
            $userId = $subscriber->user_id ?? null;

            if (!$userId) {
                echo "  ❌ Skipping subscriber {$subscriber->id}: missing user_id\n";
                continue;
            }

            if (!isset($serviceCache[$userId])) {
                try {
                    $serviceCache[$userId] = new WhatsAppService($userId);
                } catch (Exception $e) {
                    echo "  ❌ Skipping subscriber {$subscriber->id}: " . $e->getMessage() . "\n";
                    $serviceCache[$userId] = null;
                }
            }

            if (!$serviceCache[$userId]) {
                continue;
            }

            $result = $serviceCache[$userId]->sendDripCampaignStep($subscriber);
            
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
