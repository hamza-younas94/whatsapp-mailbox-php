<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpCommand extends Model
{
    protected $table = 'ip_commands';
    
    protected $fillable = [
        'user_id',
        'ip_address',
        'contact_name',
        'phone_number',
        'api_response',
        'http_code',
        'status'
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user (tenant) that owns this command
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
