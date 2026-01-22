<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $table = 'user_subscriptions';
    
    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'message_limit',
        'messages_used',
        'contact_limit',
        'features',
        'trial_ends_at',
        'current_period_start',
        'current_period_end'
    ];
    
    protected $casts = [
        'message_limit' => 'integer',
        'messages_used' => 'integer',
        'contact_limit' => 'integer',
        'features' => 'array',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime'
    ];
    
    /**
     * Get the user that owns the subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Check if user can send more messages
     */
    public function canSendMessage()
    {
        if ($this->status !== 'active') {
            return false;
        }
        
        return $this->messages_used < $this->message_limit;
    }
    
    /**
     * Increment message usage
     */
    public function incrementMessageUsage()
    {
        $this->increment('messages_used');
    }
    
    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->current_period_end && $this->current_period_end->isPast();
    }
    
    /**
     * Check if on trial
     */
    public function isOnTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
    
    /**
     * Get remaining messages
     */
    public function remainingMessages()
    {
        return max(0, $this->message_limit - $this->messages_used);
    }
    
    /**
     * Check if user has access to a specific feature
     */
    public function hasFeature($featureName)
    {
        if (empty($this->features)) {
            return false;
        }
        
        return isset($this->features[$featureName]) && $this->features[$featureName] === true;
    }
    
    /**
     * Get all enabled features
     */
    public function getEnabledFeatures()
    {
        if (empty($this->features)) {
            return [];
        }
        
        return array_keys(array_filter($this->features, function($value) {
            return $value === true;
        }));
    }
}
