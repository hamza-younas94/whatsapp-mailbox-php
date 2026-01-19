<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{
    protected $table = 'user_settings';
    
    protected $fillable = [
        'user_id',
        'whatsapp_api_version',
        'whatsapp_access_token',
        'whatsapp_phone_number_id',
        'phone_number',
        'business_name',
        'webhook_verify_token',
        'webhook_url',
        'is_configured',
        'last_verified_at'
    ];
    
    protected $casts = [
        'is_configured' => 'boolean',
        'last_verified_at' => 'datetime'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Generate a unique webhook verify token for this user
     */
    public function generateWebhookToken()
    {
        $this->webhook_verify_token = bin2hex(random_bytes(32));
        $this->save();
        return $this->webhook_verify_token;
    }
    
    /**
     * Check if API credentials are configured
     */
    public function isValid()
    {
        return $this->is_configured && 
               !empty($this->whatsapp_access_token) && 
               !empty($this->whatsapp_phone_number_id);
    }
}
