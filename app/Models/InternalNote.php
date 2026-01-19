<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalNote extends Model
{
    protected $table = 'internal_notes';
    
    protected $fillable = [
        'user_id',
        'contact_id',
        'created_by',
        'content',
        'is_pinned'
    ];
    
    protected $casts = [
        'is_pinned' => 'boolean'
    ];
    
    /**
     * Get the user (tenant) that owns this note
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
