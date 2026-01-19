<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowExecution extends Model
{
    protected $table = 'workflow_executions';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'workflow_id',
        'contact_id',
        'status',
        'actions_performed',
        'error_message',
        'executed_at'
    ];
    
    protected $casts = [
        'actions_performed' => 'array',
        'executed_at' => 'datetime'
    ];
    
    /**
     * Get the user (tenant) that owns this execution
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Workflow
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
    
    /**
     * Contact
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
