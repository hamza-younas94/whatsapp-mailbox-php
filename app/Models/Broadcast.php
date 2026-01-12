<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $table = 'broadcasts';
    
    protected $fillable = [
        'name',
        'message',
        'message_type',
        'template_name',
        'scheduled_at',
        'started_at',
        'completed_at',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'delivered_count',
        'read_count',
        'created_by'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    /**
     * Recipients of this broadcast
     */
    public function recipients()
    {
        return $this->hasMany(BroadcastRecipient::class);
    }
    
    /**
     * Creator
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
    
    /**
     * Get success rate
     */
    public function getSuccessRateAttribute()
    {
        if ($this->total_recipients == 0) return 0;
        return round(($this->sent_count / $this->total_recipients) * 100, 1);
    }
}
