<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $fillable = [
        'user_id',
        'contact_id',
        'created_by',
        'content',
        'type'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user (tenant) that owns this note
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact that owns the note
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created the note
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
