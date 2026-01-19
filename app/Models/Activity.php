<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'contact_id',
        'type',
        'title',
        'description',
        'metadata',
        'created_by'
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user (tenant) that owns the activity
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contact that owns the activity
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the user who created the activity
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    /**
     * Scope to filter by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }
}
