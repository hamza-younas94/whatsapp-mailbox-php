<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'user_id', // MULTI-TENANT: Each contact belongs to a user
        'phone_number',
        'name',
        'profile_picture_url',
        'last_message_time',
        'unread_count',
        // CRM fields
        'stage',
        'lead_score',
        'assigned_to',
        'source',
        'company_name',
        'email',
        'city',
        'country',
        'tags',
        'last_activity_at',
        'last_activity_type',
        'deal_value',
        'deal_currency',
        'expected_close_date',
        'custom_fields',
        'is_archived'
    ];

    protected $casts = [
        'last_message_time' => 'datetime',
        'unread_count' => 'integer',
        'lead_score' => 'integer',
        'last_activity_at' => 'datetime',
        'deal_value' => 'decimal:2',
        'expected_close_date' => 'date',
        'custom_fields' => 'array',
        'is_archived' => 'boolean'
    ];

    /**
     * Get the user (tenant) that owns this contact
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
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
     * Get notes for this contact
     */
    public function notes()
    {
        return $this->hasMany(Note::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get activities for this contact
     */
    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get deals/transactions for this contact
     */
    public function deals()
    {
        return $this->hasMany(Deal::class)->orderBy('deal_date', 'desc');
    }

    /**
     * Get won deals
     */
    public function wonDeals()
    {
        return $this->hasMany(Deal::class)->where('status', 'won');
    }

    /**
     * Get total revenue from this customer (won deals)
     */
    public function getTotalRevenueAttribute()
    {
        return $this->wonDeals()->sum('amount');
    }

    /**
     * Get deal count
     */
    public function getDealCountAttribute()
    {
        return $this->deals()->count();
    }

    /**
     * Get won deal count
     */
    public function getWonDealCountAttribute()
    {
        return $this->wonDeals()->count();
    }

    /**
     * Get assigned user
     */
    public function assignedUser()
    {
        return $this->belongsTo(AdminUser::class, 'assigned_to');
    }

    /**
     * Tags assigned to this contact
     */
    public function contactTags()
    {
        return $this->belongsToMany(Tag::class, 'contact_tag');
    }

    /**
     * Scheduled messages for this contact
     */
    public function scheduledMessages()
    {
        return $this->hasMany(ScheduledMessage::class);
    }

    /**
     * Get tags as array
     */
    public function getTagsArrayAttribute()
    {
        return $this->tags ? explode(',', $this->tags) : [];
    }

    /**
     * Set tags from array
     */
    public function setTagsFromArray($tags)
    {
        $this->tags = is_array($tags) ? implode(',', $tags) : $tags;
    }

    /**
     * Add activity log
     */
    public function addActivity($type, $title, $description = null, $metadata = null)
    {
        return Activity::create([
            'contact_id' => $this->id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'created_by' => $_SESSION['user_id'] ?? null
        ]);
    }

    /**
     * Update lead score
     */
    public function updateLeadScore()
    {
        $score = 0;
        
        // Has email: +10
        if ($this->email) $score += 10;
        
        // Has company: +15
        if ($this->company_name) $score += 15;
        
        // Message count: +1 per message (max 20)
        $messageCount = $this->messages()->count();
        $score += min($messageCount, 20);
        
        // Recent activity: +10
        if ($this->last_activity_at && $this->last_activity_at->diffInDays(now()) < 7) {
            $score += 10;
        }
        
        // Has deal value: +20
        if ($this->deal_value > 0) $score += 20;
        
        // Stage bonus
        $stageScores = [
            'new' => 0,
            'contacted' => 5,
            'qualified' => 10,
            'proposal' => 15,
            'negotiation' => 20,
            'customer' => 25
        ];
        $score += $stageScores[$this->stage] ?? 0;
        
        $this->update(['lead_score' => min($score, 100)]);
    }

    /**
     * Change stage and log activity
     */
    public function changeStage($newStage)
    {
        $oldStage = $this->stage;
        $this->update(['stage' => $newStage]);
        
        $this->addActivity(
            'stage_changed',
            "Stage changed from {$oldStage} to {$newStage}",
            null,
            ['old_stage' => $oldStage, 'new_stage' => $newStage]
        );
        
        $this->updateLeadScore();
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
