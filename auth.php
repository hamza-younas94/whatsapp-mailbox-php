<?php
/**
 * Authentication System with Eloquent ORM
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\AdminUser;

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

        // Find user
        $user = AdminUser::findByUsername($username);
        
        if ($user && $user->verifyPassword($password)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            
            // Update last login
            $user->updateLastLogin();
            
            return true;
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
        return AdminUser::find($_SESSION['user_id']);
    } catch (\Exception $e) {
        logger('Get current user error: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Change password
 */
function changePassword($userId, $newPassword) {
    try {
        $user = AdminUser::find($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->password = $newPassword;
        return $user->save();
    } catch (\Exception $e) {
        logger('Change password error: ' . $e->getMessage(), 'error');
        return false;
    }
}
