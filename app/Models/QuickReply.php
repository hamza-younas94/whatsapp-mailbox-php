<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuickReply extends Model
{
    protected $table = 'quick_replies';
    
    protected $fillable = [
        'shortcut',
        'title',
        'message',
        'is_active',
        'usage_count',
        'created_by'
    ];
    
    protected $casts = [
        'is_active' => 'boolean'
    ];
    
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
    }
}
