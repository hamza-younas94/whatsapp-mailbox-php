<?php

namespace App\Models;



namespace App\Models;



class Note extends Model
{
    protected $fillable = [
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
