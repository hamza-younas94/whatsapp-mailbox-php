<?php
/**
 * Authentication System with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\User;

function getDefaultFeaturesForPlan($plan) {
    $defaults = [
        'free' => [
            'mailbox' => true,
            'quick_replies' => true,
            'auto_reply' => true
        ],
        'starter' => [
            'mailbox' => true,
            'quick_replies' => true,
            'broadcasts' => true,
            'segments' => true,
            'auto_reply' => true,
            'tags' => true,
            'notes' => true
        ],
        'pro' => [
            'mailbox' => true,
            'quick_replies' => true,
            'broadcasts' => true,
            'segments' => true,
            'drip_campaigns' => true,
            'scheduled_messages' => true,
            'auto_reply' => true,
            'tags' => true,
            'notes' => true,
            'message_templates' => true,
            'crm' => true,
            'analytics' => true
        ],
        'enterprise' => [
            'mailbox' => true,
            'quick_replies' => true,
            'broadcasts' => true,
            'segments' => true,
            'drip_campaigns' => true,
            'scheduled_messages' => true,
            'auto_reply' => true,
            'tags' => true,
            'notes' => true,
            'message_templates' => true,
            'crm' => true,
            'analytics' => true,
            'workflows' => true,
            'dcmb_ip_commands' => true
        ]
    ];

    return $defaults[$plan] ?? [];
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Login user
 */
function login($username, $password) {
    try {
        // Validate input
        $validation = validate([
            'username' => $username,
            'password' => $password
        ], [
            'username' => 'required|min:3',
            'password' => 'required|min:6'
        ]);

        if ($validation !== true) {
            return false;
        }

        // Find user in users table first
        $user = User::where('username', $username)->first();
        
        // Fallback to admin_users if not found in users table
        if (!$user && class_exists('App\Models\AdminUser')) {
            $adminUser = \App\Models\AdminUser::findByUsername($username);
            if ($adminUser && $adminUser->verifyPassword($password)) {
                // Migrate admin user to users table
                $user = User::create([
                    'username' => $adminUser->username,
                    'email' => $adminUser->email ?? null,
                    'password' => $adminUser->password_hash,
                    'role' => 'admin',
                    'name' => $adminUser->username,
                    'is_active' => true
                ]);
            }
        }
        
        if ($user) {
            // Check password (User model has verifyPassword method)
            $passwordValid = false;
            if (method_exists($user, 'verifyPassword')) {
                $passwordValid = $user->verifyPassword($password);
            } else {
                // Fallback: check password_hash or password field
                $hash = $user->password_hash ?? $user->password ?? null;
                $passwordValid = $hash && password_verify($password, $hash);
            }
            
            if ($passwordValid) {
                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                
                // Update last login
                if (method_exists($user, 'update')) {
                    $user->update(['last_login_at' => now()]);
                }
                
                return true;
            }
        }
        
        return false;
    } catch (\Exception $e) {
        logger('Login error: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Avoid redeclaring when helpers.php already defines getCurrentUser()
if (!function_exists('getCurrentUser')) {
    /**
     * Get current user
     */
    function getCurrentUser() {
        if (!isAuthenticated()) {
            return null;
        }
        
        try {
            $userId = $_SESSION['user_id'];
            
            // Try to get from User model first (new system)
            $user = \App\Models\User::find($userId);
            if ($user) {
                return $user;
            }
            
            // No user found
            return null;
        } catch (\Exception $e) {
            logger('Get current user error: ' . $e->getMessage(), 'error');
            return null;
        }
    }
}

/**
 * Check if current user has permission
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // If user has can() method (User model)
    if (method_exists($user, 'can')) {
        return $user->can($permission);
    }
    
    // AdminUser is always admin (full permissions)
    if ($user instanceof AdminUser) {
        return true;
    }
    
    return false;
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    // Check role property
    if (isset($user->role)) {
        return $user->role === 'admin';
    }
    
    return false;
}

/**
 * Get current user's subscription
 */
function getUserSubscription() {
    $user = getCurrentUser();
    if (!$user) {
        return null;
    }
    
    $subscription = \App\Models\UserSubscription::where('user_id', $user->id)->first();

    if (!$subscription) {
        $subscription = \App\Models\UserSubscription::create([
            'user_id' => $user->id,
            'plan' => 'free',
            'status' => 'active',
            'message_limit' => 100,
            'messages_used' => 0,
            'contact_limit' => 50,
            'features' => getDefaultFeaturesForPlan('free'),
            'current_period_start' => date('Y-m-d H:i:s'),
            'current_period_end' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ]);
    } elseif (empty($subscription->features)) {
        $subscription->update([
            'features' => getDefaultFeaturesForPlan($subscription->plan ?? 'free')
        ]);
    }

    return $subscription;
}

/**
 * Check if current user has access to a feature
 */
function hasFeature($featureName) {
    // Admin always has all features
    if (isAdmin()) {
        return true;
    }
    
    $subscription = getUserSubscription();
    if (!$subscription) {
        return false;
    }
    
    return $subscription->hasFeature($featureName);
}

/**
 * Require feature access (redirect if not available)
 */
function requireFeature($featureName, $redirectUrl = 'index.php') {
    if (!hasFeature($featureName)) {
        $_SESSION['error'] = 'This feature is not available in your subscription plan.';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Change password
 */
function changePassword($userId, $newPassword) {
    try {
        $user = User::find($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
        return $user->save();
    } catch (\Exception $e) {
        logger('Change password error: ' . $e->getMessage(), 'error');
        return false;
    }
}
