<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ContactMerge extends Model
{
    protected $table = 'contact_merges';
    
    protected $fillable = [
        'source_contact_id',
        'target_contact_id',
        'merged_by',
        'merge_reason',
        'merged_data'
    ];
    
    protected $casts = [
        'merged_data' => 'array'
    ];
    
    /**
     * Source contact (the one that was merged into)
     */
    public function sourceContact()
    {
        return $this->belongsTo(Contact::class, 'source_contact_id');
    }
    
    /**
     * Target contact (the one that kept the data)
     */
    public function targetContact()
    {
        return $this->belongsTo(Contact::class, 'target_contact_id');
    }
    
    /**
     * User who performed the merge
     */
    public function mergedBy()
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}

