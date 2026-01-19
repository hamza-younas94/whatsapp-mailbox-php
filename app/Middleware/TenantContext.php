<?php

namespace App\Middleware;

/**
 * Tenant Context Middleware
 * 
 * Automatically filters all database queries to include user_id
 * This ensures complete data isolation between tenants
 */
class TenantContext
{
    private static $currentUserId = null;
    private static $bypassTenantFilter = false;
    
    /**
     * Set the current tenant (user)
     */
    public static function setUser($userId)
    {
        self::$currentUserId = $userId;
        
        // Apply global scope to all models that need tenant filtering
        self::applyGlobalScopes();
    }
    
    /**
     * Get current tenant user ID
     */
    public static function getUserId()
    {
        return self::$currentUserId;
    }
    
    /**
     * Temporarily bypass tenant filtering (for admin operations)
     */
    public static function withoutTenantFilter($callback)
    {
        $previous = self::$bypassTenantFilter;
        self::$bypassTenantFilter = true;
        
        $result = $callback();
        
        self::$bypassTenantFilter = $previous;
        
        return $result;
    }
    
    /**
     * Check if tenant filter should be applied
     */
    public static function shouldFilter()
    {
        return self::$currentUserId !== null && !self::$bypassTenantFilter;
    }
    
    /**
     * Apply global scopes to all tenant-aware models
     */
    private static function applyGlobalScopes()
    {
        $tenantModels = [
            \App\Models\Contact::class,
            \App\Models\Message::class,
            \App\Models\QuickReply::class,
            \App\Models\Broadcast::class,
            \App\Models\ScheduledMessage::class,
            \App\Models\Segment::class,
            \App\Models\Tag::class,
            \App\Models\Deal::class,
            \App\Models\Workflow::class,
            \App\Models\AutoTagRule::class,
            \App\Models\Webhook::class,
            \App\Models\Note::class,
            \App\Models\InternalNote::class,
            \App\Models\Activity::class,
            \App\Models\Task::class,
            \App\Models\MessageTemplate::class,
            \App\Models\DripCampaign::class,
            \App\Models\IpCommand::class,
        ];
        
        foreach ($tenantModels as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::addGlobalScope('tenant', function ($query) {
                    if (self::shouldFilter()) {
                        $query->where($query->getModel()->getTable() . '.user_id', self::$currentUserId);
                    }
                });
            }
        }
    }
    
    /**
     * Initialize tenant context from session
     */
    public static function initFromSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            self::setUser($_SESSION['user_id']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current user's API credentials
     */
    public static function getApiCredentials()
    {
        if (!self::$currentUserId) {
            return null;
        }
        
        return \App\Models\UserApiCredential::where('user_id', self::$currentUserId)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Get current user's subscription
     */
    public static function getSubscription()
    {
        if (!self::$currentUserId) {
            return null;
        }
        
        return \App\Models\UserSubscription::where('user_id', self::$currentUserId)
            ->first();
    }
    
    /**
     * Check if current user can perform action
     */
    public static function can($action)
    {
        $subscription = self::getSubscription();
        
        if (!$subscription) {
            return false;
        }
        
        switch ($action) {
            case 'send_message':
                return $subscription->canSendMessage();
            case 'create_contact':
                $contactCount = \App\Models\Contact::count();
                return $contactCount < $subscription->contact_limit;
            default:
                return true;
        }
    }
}
