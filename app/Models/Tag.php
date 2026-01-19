<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'tags';
    
    protected $fillable = [
        'user_id',
        'name',
        'color',
        'description'
    ];
    
    /**
     * Owner of this tag
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contacts that have this tag
     */
    public function contacts()
    {
        return $this->belongsToMany(Contact::class, 'contact_tag');
    }
    
    /**
     * Get contact count for this tag
     */
    public function getContactCountAttribute()
    {
        return $this->contacts()->count();
    }
}
