<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DripCampaign extends Model
{
    protected $table = 'drip_campaigns';
    
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'trigger_conditions',
        'total_subscribers',
        'completed_count',
        'created_by'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'trigger_conditions' => 'array',
        'total_subscribers' => 'integer',
        'completed_count' => 'integer'
    ];
    
    public function steps()
    {
        return $this->hasMany(DripCampaignStep::class, 'campaign_id')->orderBy('step_number');
    }
    
    public function subscribers()
    {
        return $this->hasMany(DripSubscriber::class, 'campaign_id');
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function activeSubscribers()
    {
        return $this->subscribers()->where('status', 'active');
    }
}

class DripCampaignStep extends Model
{
    protected $table = 'drip_campaign_steps';
    
    protected $fillable = [
        'campaign_id',
        'step_number',
        'name',
        'delay_minutes',
        'message_type',
        'message_content',
        'template_id',
        'sent_count'
    ];
    
    protected $casts = [
        'delay_minutes' => 'integer',
        'sent_count' => 'integer'
    ];
    
    public function campaign()
    {
        return $this->belongsTo(DripCampaign::class, 'campaign_id');
    }
    
    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }
}

