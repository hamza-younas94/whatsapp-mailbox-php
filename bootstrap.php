<?php
/**
 * Application Bootstrap
 * Initializes Eloquent ORM and loads environment variables
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configure timezone
date_default_timezone_set(env('TIMEZONE', 'UTC'));

// Initialize Logger
$log = new Logger('whatsapp-mailbox');
$log->pushHandler(new StreamHandler(__DIR__ . '/storage/logs/app.log', Logger::ERROR));

// Initialize Eloquent ORM
$capsule = new Capsule;

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

// Set the event dispatcher
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make Eloquent available globally
$capsule->setAsGlobal();
$caInitialize Twig templating engine
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, [
    'cache' => env('APP_ENV') === 'production' ? __DIR__ . '/storage/cache/twig' : false,
    'debug' => env('APP_DEBUG', false),
    'auto_reload' => env('APP_ENV') !== 'production'
]);

// Add global variables to Twig
$twig->addGlobal('app_name', env('APP_NAME', 'WhatsApp Mailbox'));
$twig->addGlobal('app_url', env('APP_URL', ''));

// Make Twig available globally
$GLOBALS['twig'] = $twig;

// psule->bootEloquent();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', env('SESSION_SECURE', 1));
    session_start();
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
