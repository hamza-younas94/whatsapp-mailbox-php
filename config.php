<?php
// Database Configuration
define('DB_HOST', 'localhost'); // Usually 'localhost' on shared hosting
define('DB_NAME', 'messagehub'); // Your database name
define('DB_USER', 'your_db_username'); // Your database username
define('DB_PASS', 'your_db_password'); // Your database password

// Business Messaging API Configuration (Meta/Facebook Business Platform)
// Get these from your Facebook Developer Console
define('API_ACCESS_TOKEN', 'YOUR_ACCESS_TOKEN_HERE');
define('API_PHONE_NUMBER_ID', 'YOUR_PHONE_NUMBER_ID_HERE');
define('WEBHOOK_VERIFY_TOKEN', 'YOUR_VERIFY_TOKEN_HERE'); // You create this token

// Base URLs
define('API_BASE_URL', 'https://graph.facebook.com/v18.0');
define('BASE_URL', 'https://yourdomain.com'); // Your Namecheap domain

// Timezone
date_default_timezone_set('UTC');

// Database Connection Class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
