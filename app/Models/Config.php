<?php

namespace App\Models;



namespace App\Models;



class Config extends Model
{
    protected $table = 'config';

    protected $fillable = [
        'config_key',
        'config_value'
    ];

    /**
     * Get config value by key
     */
    public static function get($key, $default = null)
    {
        $config = static::where('config_key', $key)->first();
        return $config ? $config->config_value : $default;
    }

    /**
     * Set config value
     */
    public static function set($key, $value)
    {
        return static::updateOrCreate(
            ['config_key' => $key],
            ['config_value' => $value]
        );
    }

    /**
     * Get all config as key-value array
     */
    public static function getAll()
    {
        return static::pluck('config_value', 'config_key')->toArray();
    }
}
