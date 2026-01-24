<?php
/**
 * Helper Functions
 */

if (!function_exists('env')) {
    /**
     * Get environment variable value
     */
    function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key, $default = null)
    {
        return \App\Models\Config::get($key, $default);
    }
}

if (!function_exists('now')) {
    /**
     * Get current datetime
     */
    function now()
    {
        return new DateTime();
    }
}

if (!function_exists('response_json')) {
    /**
     * Send JSON response
     */
    function response_json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('response_error')) {
    /**
     * Send error response
     */
    function response_error($message, $statusCode = 400, $errors = [])
    {
        response_json([
            'error' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}

if (!function_exists('validate')) {
    /**
     * Simple validation helper
     */
    function validate($data, $rules)
    {
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            
            foreach ($ruleList as $rule) {
                if ($rule === 'required' && empty($value)) {
                    $errors[$field][] = "The {$field} field is required.";
                }
                
                if ($rule === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email.";
                }
                
                if (strpos($rule, 'min:') === 0 && $value) {
                    $min = (int) substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }
                
                if (strpos($rule, 'max:') === 0 && $value) {
                    $max = (int) substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The {$field} must not exceed {$max} characters.";
                    }
                }
                
                if ($rule === 'number' && $value && !is_numeric($value)) {
                    $errors[$field][] = "The {$field} must be a number.";
                }
            }
        }
        
        return empty($errors) ? true : $errors;
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitize input data
     */
    function sanitize($data)
    {
        if (is_array($data)) {
            return array_map('sanitize', $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('audit_log')) {
    /**
     * Write an audit log entry (best-effort)
     */
    function audit_log(string $action, string $entityType, $entityId = null, array $metadata = [])
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            \Illuminate\Database\Capsule\Manager::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => json_encode($metadata),
                'ip_address' => $ip,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // best-effort; do not block user action
        }
    }
}

if (!function_exists('logger')) {
    /**
     * Log message
     */
    function logger($message, $level = 'info')
    {
        $log = new \Monolog\Logger('app');
        $log->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . '/../storage/logs/app.log'));
        
        $log->{$level}($message);
    }
}

if (!function_exists('view')) {
    /**
     * Render a Twig template
     */
    function view($template, $data = [])
    {
        // Get Twig from global or GLOBALS
        if (isset($GLOBALS['twig'])) {
            $twig = $GLOBALS['twig'];
        } else {
            global $twig;
        }
        
        if (!$twig) {
            throw new \Exception('Twig not initialized. Make sure bootstrap.php is loaded.');
        }
        
        return $twig->render($template, $data);
    }
}

if (!function_exists('render')) {
    /**
     * Render and output a Twig template
     */
    function render($template, $data = [])
    {
        echo view($template, $data);
    }
}

if (!function_exists('getCurrentUser')) {
    /**
     * Get the currently authenticated user
     */
    function getCurrentUser()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            $user = \App\Models\User::find($_SESSION['user_id']);
            return $user;
        } catch (\Exception $e) {
            error_log('Error getting current user: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('auth')) {
    /**
     * Authentication helper - returns an auth guard object
     */
    function auth()
    {
        return new class {
            public function check()
            {
                return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
            }
            
            public function user()
            {
                return getCurrentUser();
            }
            
            public function id()
            {
                return $_SESSION['user_id'] ?? null;
            }
            
            public function guest()
            {
                return !$this->check();
            }
        };
    }
}
