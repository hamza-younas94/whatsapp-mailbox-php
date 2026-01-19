<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'message_id',
        'contact_id',
        'phone_number',
        'message_type',
        'direction',
        'message_body',
        'media_url',
        'media_id',
        'media_filename',
        'media_size',
        'media_mime_type',
        'media_caption',
        'status',
        'is_read',
        'timestamp'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'timestamp' => 'datetime'
    ];

    /**
     * Get the user (tenant) that owns this message
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact that owns this message
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Scope to get incoming messages only
     */
    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    /**
     * Scope to get outgoing messages only
     */
    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to search messages by content
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('message_body', 'like', "%{$searchTerm}%");
    }

    /**
     * Mark this message as read
     */
    public function markAsRead()
    {
        if (!$this->is_read && $this->direction === 'incoming') {
            $this->update(['is_read' => true]);
            
            // Decrement contact unread count
            if ($this->contact && $this->contact->unread_count > 0) {
                $this->contact->decrement('unread_count');
            }
        }
    }

    /**
     * Get status icon for display
     */
    public function getStatusIconAttribute()
    {
        if ($this->direction !== 'outgoing') {
            return '';
        }

        return match($this->status) {
            'sent' => '✓',
            'delivered' => '✓✓',
            'read' => '✓✓',
            'failed' => '✗',
            default => ''
        };
    }
}
