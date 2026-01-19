<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Workflow extends Model
{
    protected $table = 'workflows';
    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'actions',
        'is_active',
        'execution_count',
        'last_executed_at',
        'created_by'
    ];
    
    protected $casts = [
        'trigger_conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'last_executed_at' => 'datetime'
    ];
    
    /**
     * Get the user (tenant) that owns this workflow
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Executions
     */
    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }
    
    /**
     * Creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
