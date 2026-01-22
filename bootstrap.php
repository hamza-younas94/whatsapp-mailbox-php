<?php
/**
 * Application Bootstrap
 * Initializes Eloquent ORM and loads environment variables
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load helper functions
require_once __DIR__ . '/app/helpers.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Twig\TwigFunction;

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    // If .env doesn't exist, try to continue with environment variables
    if (!file_exists(__DIR__ . '/.env')) {
        error_log('WARNING: .env file not found. Using default/system environment variables.');
    } else {
        throw $e;
    }
}

// Configure timezone
date_default_timezone_set(env('TIMEZONE', 'UTC'));

// Initialize Eloquent ORM
$capsule = new Capsule;

try {
    $capsule->addConnection([
        'driver' => env('DB_CONNECTION', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ]);
} catch (Exception $e) {
    error_log("Database configuration error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "ERROR: Database configuration failed. Check your .env file.\n";
        echo "Details: " . $e->getMessage() . "\n";
    }
    throw $e;
}

// Set the event dispatcher
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make Eloquent available globally
$capsule->setAsGlobal();

// Boot Eloquent
$capsule->bootEloquent();

// Auto-run migrations on bootstrap (only in web requests, not CLI)
// This ensures database schema is always up-to-date when the app loads
if (php_sapi_name() !== 'cli' && file_exists(__DIR__ . '/migrate.php')) {
    try {
        require_once __DIR__ . '/migrate.php';
        $migrationRunner = new MigrationRunner();
        $migrationRunner->run(true); // Silent mode - no output (logs errors only)
    } catch (Exception $e) {
        // Log migration errors but don't break the app
        error_log("Migration auto-run error: " . $e->getMessage());
        if (env('APP_DEBUG', false)) {
            error_log("Migration stack trace: " . $e->getTraceAsString());
        }
    }
}

// Initialize Twig templating engine
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'cache' => env('APP_ENV') === 'production' ? __DIR__ . '/storage/cache/twig' : false,
    'debug' => env('APP_DEBUG', false),
    'auto_reload' => env('APP_ENV') !== 'production'
]);

// Add global variables to Twig
$twig->addGlobal('app_name', env('APP_NAME', 'MessageHub'));
$twig->addGlobal('app_url', env('APP_URL', ''));

// Add Twig functions
$twig->addFunction(new TwigFunction('getLevelColor', function($level) {
    $colors = [
        'ERROR' => 'danger',
        'WARNING' => 'warning',
        'INFO' => 'info',
        'DEBUG' => 'secondary',
        'CRITICAL' => 'danger',
        'ALERT' => 'warning',
        'EMERGENCY' => 'danger'
    ];
    return $colors[$level] ?? 'secondary';
}));

// Make Twig available globally
$GLOBALS['twig'] = $twig;
global $twig; // Also make it available as global variable for view() function

// Start session (skip for webhooks)
if (!defined('NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', env('SESSION_SECURE', 1));
    session_start();
}

// Session idle timeout enforcement
if (!defined('NO_SESSION') && session_status() === PHP_SESSION_ACTIVE) {
    $maxIdle = (int) env('SESSION_IDLE_TIMEOUT', 1800); // default 30 minutes
    $now = time();

    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > $maxIdle) {
        $_SESSION = [];
        session_destroy();

        if (php_sapi_name() !== 'cli') {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                http_response_code(440);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Session expired']);
                exit;
            }

            header('Location: /login.php?timeout=1');
            exit;
        }
    }

    $_SESSION['last_activity'] = $now;
}

// Security headers and HTTPS enforcement (web requests only)
if (php_sapi_name() !== 'cli') {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    if (env('FORCE_HTTPS', true) && !$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' data: https:; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; connect-src 'self' https: wss:;");

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Error handling
if (env('APP_DEBUG', false)) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/storage/logs/error.log');
}

return $capsule;
