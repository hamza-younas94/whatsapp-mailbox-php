<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Task extends Model
{
    protected $table = 'tasks';
    
    protected $fillable = [
        'user_id',
        'contact_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'assigned_to',
        'created_by',
        'notes'
    ];
    
    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime'
    ];
    
    /**
     * Get the user (tenant) that owns this task
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
    
    /**
     * User assigned to this task
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    /**
     * User who created this task
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Mark task as completed
     */
    public function markCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }
    
    /**
     * Check if task is overdue
     */
    public function isOverdue()
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               $this->status !== 'completed' && 
               $this->status !== 'cancelled';
    }
}

