<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $table = 'webhooks';
    
    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'success_count',
        'failure_count',
        'last_triggered_at'
    ];
    
    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'last_triggered_at' => 'datetime'
    ];
    
    /**
     * Check if webhook listens to event
     */
    public function listensTo($event)
    {
        return in_array($event, $this->events);
    }
    
    /**
     * Trigger webhook
     */
    public function trigger($event, $payload)
    {
        if (!$this->is_active || !$this->listensTo($event)) {
            return false;
        }
        
        try {
            $signature = hash_hmac('sha256', json_encode($payload), $this->secret ?? '');
            
            $ch = curl_init($this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . $signature,
                'X-Webhook-Event: ' . $event
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->increment('success_count');
                $this->update(['last_triggered_at' => now()]);
                return true;
            } else {
                $this->increment('failure_count');
                return false;
            }
        } catch (\Exception $e) {
            $this->increment('failure_count');
            logger('Webhook error: ' . $e->getMessage(), 'error');
            return false;
        }
    }
}
