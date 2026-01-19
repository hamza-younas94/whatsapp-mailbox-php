<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Segment extends Model
{
    protected $table = 'segments';
    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'conditions',
        'is_dynamic',
        'contact_count',
        'created_by'
    ];
    
    protected $casts = [
        'conditions' => 'array',
        'is_dynamic' => 'boolean'
    ];
    
    /**
     * Owner of this segment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Creator
     */
    public function creator()
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
    
    /**
     * Get contacts matching this segment
     */
    public function getContacts()
    {
        $query = Contact::query();
        
        foreach ($this->conditions as $field => $condition) {
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            
            switch ($field) {
                case 'stage':
                    if ($operator === 'in' && is_array($value)) {
                        $query->whereIn('stage', $value);
                    } else {
                        $query->where('stage', $operator, $value);
                    }
                    break;
                
                case 'lead_score':
                    $query->where('lead_score', $operator, $value);
                    break;
                
                case 'last_message_days':
                    $days = intval($value);
                    $date = now()->subDays($days);
                    $query->where('last_message_at', $operator === '>' ? '<' : '>', $date);
                    break;
            }
        }
        
        return $query->get();
    }
    
    /**
     * Update contact count
     */
    public function updateContactCount()
    {
        $this->contact_count = $this->getContacts()->count();
        $this->save();
    }
}
