<?php
/**
 * Authentication System with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\User;

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
