<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    protected $table = 'admin_users';

    protected $fillable = [
        'username',
        'password',
        'password_hash',
        'email',
        'last_login',
        'is_active'
    ];

    protected $hidden = [
        'password_hash'
    ];

    protected $casts = [
        'last_login' => 'datetime'
    ];

    /**
     * Hash password before saving
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Alias for password_hash
     */
    public function setPasswordHashAttribute($password)
    {
        $this->attributes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password_hash);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin()
    {
        $this->update(['last_login' => now()]);
    }

    /**
     * Find user by username
     */
    public static function findByUsername($username)
    {
        return static::where('username', $username)->first();
    }
}
