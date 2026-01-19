<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoTagRule extends Model
{
    protected $table = 'auto_tag_rules';
    
    protected $fillable = [
        'user_id',
        'name',
        'keywords',
        'tag_id',
        'is_active',
        'case_sensitive',
        'match_type',
        'priority',
        'usage_count'
    ];
    
    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'case_sensitive' => 'boolean',
        'usage_count' => 'integer',
        'priority' => 'integer'
    ];
    
    /**
     * Owner of this rule
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tag that will be assigned
     */
    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
    
    /**
     * Check if message matches this rule
     */
    public function matches($message)
    {
        $text = $this->case_sensitive ? $message : strtolower($message);
        $keywords = $this->case_sensitive ? $this->keywords : array_map('strtolower', $this->keywords);
        
        switch ($this->match_type) {
            case 'all':
                // All keywords must be present
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) === false) {
                        return false;
                    }
                }
                return true;
                
            case 'exact':
                // Message must exactly match one of the keywords
                $messageText = $this->case_sensitive ? trim($message) : strtolower(trim($message));
                return in_array($messageText, $keywords);
                
            case 'any':
            default:
                // Any keyword must be present
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
        }
    }
    
    /**
     * Increment usage counter
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }
}
