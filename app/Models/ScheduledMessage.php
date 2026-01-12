<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    protected $table = 'scheduled_messages';
    
    protected $fillable = [
        'contact_id',
        'message',
        'message_type',
        'template_name',
        'scheduled_at',
        'sent_at',
        'status',
        'whatsapp_message_id',
        'error_message',
        'is_recurring',
        'recurrence_pattern',
        'created_by'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'is_recurring' => 'boolean'
    ];
    
    /**
     * Contact this message is for
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
    
    /**
     * Creator
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
    
    /**
     * Check if message is due to be sent
     */
    public function isDue()
    {
        return $this->status === 'pending' && 
               $this->scheduled_at <= now();
    }
}
