<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DripSubscriber extends Model
{
    protected $table = 'drip_subscribers';
    
    protected $fillable = [
        'user_id',
        'campaign_id',
        'contact_id',
        'current_step',
        'status',
        'next_send_at',
        'completed_at',
        'unsubscribed_at'
    ];
    
    protected $casts = [
        'next_send_at' => 'datetime',
        'completed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'current_step' => 'integer'
    ];
    
    /**
     * Get the user (tenant) that owns this subscriber
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the drip campaign
     */
    public function campaign()
    {
          return $this->belongsTo(DripCampaign::class, 'campaign_id');
    }
    
    /**
     * Get the contact
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
    
    /**
     * Check if subscriber is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }
    
    /**
     * Check if subscriber is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }
    
    /**
     * Check if subscriber is unsubscribed
     */
    public function isUnsubscribed()
    {
        return $this->status === 'unsubscribed';
    }
}

