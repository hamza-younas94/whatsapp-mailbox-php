<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    protected $table = 'quick_replies';
    
    protected $fillable = [
        'user_id',
        'shortcut',
        'shortcuts',
        'title',
        'message',
        'is_active',
        'priority',
        'usage_count',
        'success_count',
        'failure_count',
        'last_used_at',
        'created_by',
        'business_hours_start',
        'business_hours_end',
        'timezone',
        'outside_hours_message',
        'conditions',
        'use_regex',
        'delay_seconds',
        'media_url',
        'media_type',
        'media_filename',
        'excluded_contact_ids',
        'included_contact_ids',
        'sequence_messages',
        'sequence_delay_seconds',
        'allow_groups'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'use_regex' => 'boolean',
        'allow_groups' => 'boolean',
        'shortcuts' => 'array',
        'conditions' => 'array',
        'excluded_contact_ids' => 'array',
        'included_contact_ids' => 'array',
        'sequence_messages' => 'array',
        'priority' => 'integer',
        'usage_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'delay_seconds' => 'integer',
        'sequence_delay_seconds' => 'integer',
        'last_used_at' => 'datetime',
        'business_hours_start' => 'datetime',
        'business_hours_end' => 'datetime'
    ];
    
    /**
     * Owner of this quick reply
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Creator of this quick reply
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
    
    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }
    
    /**
     * Increment success count
     */
    public function incrementSuccess()
    {
        $this->increment('success_count');
    }
    
    /**
     * Increment failure count
     */
    public function incrementFailure()
    {
        $this->increment('failure_count');
    }
    
    /**
     * Get all shortcuts (including legacy shortcut field)
     */
    public function getAllShortcuts()
    {
        $shortcuts = [];
        
        // Get from shortcuts array (new system)
        if (!empty($this->shortcuts) && is_array($this->shortcuts)) {
            $shortcuts = array_merge($shortcuts, $this->shortcuts);
        }
        
        // Get from shortcut field (legacy)
        if (!empty($this->shortcut)) {
            $shortcuts[] = $this->shortcut;
        }
        
        // Remove duplicates and empty values
        return array_unique(array_filter($shortcuts));
    }
    
    /**
     * Check if this reply matches the search text
     */
    public function matches($searchText)
    {
        $shortcuts = $this->getAllShortcuts();
        $searchTextLower = strtolower(trim($searchText));
        
        foreach ($shortcuts as $shortcut) {
            $shortcutTrimmed = trim($shortcut);
            $shortcutLower = strtolower(ltrim($shortcutTrimmed, '/'));
            
            if ($this->use_regex) {
                // Regex matching
                try {
                    if (preg_match($shortcutTrimmed, $searchText)) {
                        return true;
                    }
                } catch (\Exception $e) {
                    logger("[QUICK_REPLY] Invalid regex pattern: {$shortcutTrimmed}", 'warning');
                }
            } else {
                // Exact match
                if ($searchTextLower === $shortcutLower || $searchTextLower === ltrim($shortcutLower, '/')) {
                    return true;
                }
                
                // Word boundary match
                $pattern = '/\b' . preg_quote($shortcutLower, '/') . '\b/i';
                if (preg_match($pattern, $searchText)) {
                    return true;
                }
                
                // Starts with match
                if (strpos($searchTextLower, $shortcutLower) === 0) {
                    return true;
                }
                
                // Contains match (fuzzy)
                if (strpos($searchTextLower, $shortcutLower) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if conditions are met
     */
    public function conditionsMet($contact)
    {
        if (empty($this->conditions) || !is_array($this->conditions)) {
            return true; // No conditions = always true
        }
        
        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? null;
            
            if (!$field) {
                continue;
            }
            
            $contactValue = null;
            
            switch ($field) {
                case 'tag':
                    // Check if contact has tag
                    $tag = Tag::where('name', $value)->first();
                    if ($tag) {
                        $hasTag = $contact->contactTags()->where('tag_id', $tag->id)->exists();
                        $contactValue = $hasTag;
                    }
                    break;
                    
                case 'stage':
                    $contactValue = $contact->stage ?? null;
                    break;
                    
                case 'message_count':
                    $contactValue = Message::where('contact_id', $contact->id)->count();
                    break;
                    
                case 'last_message_days':
                    $lastMessage = Message::where('contact_id', $contact->id)
                        ->orderBy('timestamp', 'desc')
                        ->first();
                    if ($lastMessage) {
                        $contactValue = (int)((time() - strtotime($lastMessage->timestamp)) / 86400);
                    } else {
                        $contactValue = 999;
                    }
                    break;
                    
                default:
                    // Try to get from contact attributes
                    $contactValue = $contact->{$field} ?? null;
            }
            
            // Evaluate condition
            switch ($operator) {
                case 'equals':
                    if ($contactValue != $value) {
                        return false;
                    }
                    break;
                    
                case 'not_equals':
                    if ($contactValue == $value) {
                        return false;
                    }
                    break;
                    
                case 'contains':
                    if (stripos($contactValue, $value) === false) {
                        return false;
                    }
                    break;
                    
                case 'greater_than':
                    if ($contactValue <= $value) {
                        return false;
                    }
                    break;
                    
                case 'less_than':
                    if ($contactValue >= $value) {
                        return false;
                    }
                    break;
                    
                case 'in':
                    if (!in_array($contactValue, (array)$value)) {
                        return false;
                    }
                    break;
                    
                case 'not_in':
                    if (in_array($contactValue, (array)$value)) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }
    
    /**
     * Check if within business hours
     */
    public function isWithinBusinessHours()
    {
        if (!$this->business_hours_start || !$this->business_hours_end) {
            return true; // No business hours = always available
        }
        
        try {
            $timezone = new \DateTimeZone($this->timezone ?? 'UTC');
            $now = new \DateTime('now', $timezone);
            $currentTime = $now->format('H:i:s');
            
            $start = $this->business_hours_start instanceof \DateTime 
                ? $this->business_hours_start->format('H:i:s')
                : date('H:i:s', strtotime($this->business_hours_start));
                
            $end = $this->business_hours_end instanceof \DateTime
                ? $this->business_hours_end->format('H:i:s')
                : date('H:i:s', strtotime($this->business_hours_end));
            
            return $currentTime >= $start && $currentTime <= $end;
        } catch (\Exception $e) {
            logger("[QUICK_REPLY] Business hours check error: " . $e->getMessage(), 'error');
            return true; // Default to available on error
        }
    }
}
