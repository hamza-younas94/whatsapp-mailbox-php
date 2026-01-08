<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'phone_number',
        'name',
        'profile_picture_url',
        'last_message_time',
        'unread_count'
    ];

    protected $casts = [
        'last_message_time' => 'datetime',
        'unread_count' => 'integer'
    ];

    /**
     * Get all messages for this contact
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('timestamp', 'desc');
    }

    /**
     * Get the last message for this contact
     */
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('timestamp');
    }

    /**
     * Get unread messages for this contact
     */
    public function unreadMessages()
    {
        return $this->hasMany(Message::class)
            ->where('direction', 'incoming')
            ->where('is_read', false);
    }

    /**
     * Mark all messages from this contact as read
     */
    public function markAllAsRead()
    {
        $this->messages()
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->update(['is_read' => true]);
        
        $this->update(['unread_count' => 0]);
    }

    /**
     * Increment unread count
     */
    public function incrementUnread()
    {
        $this->increment('unread_count');
    }

    /**
     * Get initials from name
     */
    public function getInitialsAttribute()
    {
        if (!$this->name) {
            return '?';
        }

        $parts = explode(' ', $this->name);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }
}
