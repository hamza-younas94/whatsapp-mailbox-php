<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalNote extends Model
{
    protected $table = 'internal_notes';
    
    protected $fillable = [
        'contact_id',
        'created_by',
        'content',
        'is_pinned'
    ];
    
    protected $casts = [
        'is_pinned' => 'boolean'
    ];
    
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
