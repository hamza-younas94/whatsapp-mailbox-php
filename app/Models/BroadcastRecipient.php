<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastRecipient extends Model
{
    protected $table = 'broadcast_recipients';
    
    protected $fillable = [
        'user_id',
        'broadcast_id',
        'contact_id',
        'status',
        'whatsapp_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'error_message'
    ];
    
    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime'
    ];
    
    /**
     * Get the user (tenant) that owns this recipient record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Broadcast
     */
    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class);
    }
    
    /**
     * Contact
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
