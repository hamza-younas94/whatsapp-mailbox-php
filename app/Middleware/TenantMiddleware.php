<?php

namespace App\Middleware;

use App\Models\User;

class TenantMiddleware
{
    /**
     * Ensure all queries are scoped to current user
     * This is the core of data isolation
     */
    public static function scopeToCurrentUser($model)
    {
        if (!auth()->check()) {
            return $model;
        }
        
        $userId = auth()->user()->id;
        
        // Admin users can see all data
        if (auth()->user()->role === 'admin') {
            return $model;
        }
        
        // Regular users only see their own data
        return $model->where('user_id', $userId);
    }
    
    /**
     * Add current user_id to model creation
     */
    public static function addCurrentUser(&$data)
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }
        
        $data['user_id'] = auth()->user()->id;
        return $data;
    }
    
    /**
     * Check if user can access a specific record
     */
    public static function canAccess($record)
    {
        if (!auth()->check()) {
            return false;
        }
        
        // Admin can access everything
        if (auth()->user()->role === 'admin') {
            return true;
        }
        
        // User can only access their own records
        return $record->user_id === auth()->user()->id;
    }
    
    /**
     * Check if user can access another user's data (admin only)
     */
    public static function canAccessUser($userId)
    {
        if (!auth()->check()) {
            return false;
        }
        
        // Admin can access all users
        if (auth()->user()->role === 'admin') {
            return true;
        }
        
        // Users can only access their own data
        return auth()->user()->id === $userId;
    }
}
