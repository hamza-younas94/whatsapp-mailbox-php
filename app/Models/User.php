<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'role',
        'is_active',
        'avatar_url',
        'phone',
        'last_login_at',
        'messages_sent',
        'conversations_handled',
        'avg_response_time'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'messages_sent' => 'integer',
        'conversations_handled' => 'integer',
        'avg_response_time' => 'decimal:2'
    ];
    
    protected $hidden = [
        'password'
    ];
    
    /**
     * Contacts assigned to this agent
     */
    public function assignedContacts()
    {
        return $this->hasMany(Contact::class, 'assigned_agent_id');
    }
    
    /**
     * Internal notes created by this user
     */
    public function internalNotes()
    {
        return $this->hasMany(InternalNote::class, 'created_by');
    }
    
    /**
     * Messages responded by this user
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'responded_by');
    }
    
    /**
     * Check if user has permission
     */
    public function can($permission)
    {
        $rolePermissions = [
            'admin' => ['*'], // All permissions
            'agent' => ['view_contacts', 'send_messages', 'update_crm', 'create_notes'],
            'viewer' => ['view_contacts', 'view_messages']
        ];
        
        $permissions = $rolePermissions[$this->role] ?? [];
        
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }
    
    /**
     * Update agent statistics
     */
    public function updateStats()
    {
        // Count messages sent
        $messagesSent = Message::where('responded_by', $this->id)
            ->where('direction', 'outgoing')
            ->count();
            
        // Count unique conversations
        $conversationsHandled = Contact::where('assigned_agent_id', $this->id)->count();
        
        // Calculate average response time
        $avgTime = Message::where('responded_by', $this->id)
            ->whereNotNull('response_time_minutes')
            ->avg('response_time_minutes');
        
        $this->update([
            'messages_sent' => $messagesSent,
            'conversations_handled' => $conversationsHandled,
            'avg_response_time' => $avgTime
        ]);
    }
}
