<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserApiCredential extends Model
{
    protected $table = 'user_api_credentials';
    
    protected $fillable = [
        'user_id',
        'api_access_token',
        'api_phone_number_id',
        'api_version',
        'webhook_verify_token',
        'business_name',
        'business_phone_number',
        'is_active',
        'last_webhook_at'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_webhook_at' => 'datetime'
    ];
    
    protected $hidden = [
        'api_access_token',
        'webhook_verify_token'
    ];
    
    /**
     * Get the user that owns the credentials
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get credentials by phone number ID (for webhook routing)
     */
    public static function findByPhoneNumberId($phoneNumberId)
    {
        return self::where('api_phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Update last webhook timestamp
     */
    public function recordWebhook()
    {
        $this->update(['last_webhook_at' => now()]);
    }
}
