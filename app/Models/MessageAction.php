<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Message;

class MessageAction extends Model
{
    protected $table = 'message_actions';
    
    protected $fillable = [
        'message_id',
        'user_id',
        'action_type',
        'forwarded_to_contact_id',
        'notes'
    ];
    
    /**
     * Message this action belongs to
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }
    
    /**
     * User who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Contact this message was forwarded to
     */
    public function forwardedToContact()
    {
        return $this->belongsTo(Contact::class, 'forwarded_to_contact_id');
    }
    
    /**
     * Star a message
     */
    public static function star($messageId, $userId)
    {
        return self::updateOrCreate(
            [
                'message_id' => $messageId,
                'user_id' => $userId,
                'action_type' => 'star'
            ],
            []
        );
    }
    
    /**
     * Unstar a message
     */
    public static function unstar($messageId, $userId)
    {
        return self::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('action_type', 'star')
            ->delete();
    }
    
    /**
     * Check if message is starred by user
     */
    public static function isStarred($messageId, $userId)
    {
        return self::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->where('action_type', 'star')
            ->exists();
    }
}

