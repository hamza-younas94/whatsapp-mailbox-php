<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    protected $table = 'message_templates';
    
    protected $fillable = [
        'name',
        'whatsapp_template_name',
        'language_code',
        'content',
        'variables',
        'category',
        'status',
        'usage_count',
        'created_by'
    ];
    
    protected $casts = [
        'variables' => 'array',
        'usage_count' => 'integer'
    ];
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }
}
