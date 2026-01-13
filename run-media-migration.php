<?php
/**
 * Direct migration runner for media columns
 * Run this on the server: curl https://wa.nexofydigital.com/run-media-migration.php
 */

// Direct database connection (no composer needed)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_DATABASE') ?: 'whatsapp_mailbox';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to database: {$dbName}\n";
    
    // Add media_url column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_url'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_url LONGTEXT NULL AFTER message_body");
        echo "✅ Added media_url column\n";
    } else {
        echo "⚠️  media_url column already exists\n";
    }
    
    // Add media_mime_type column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_mime_type'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_mime_type VARCHAR(255) NULL AFTER media_url");
        echo "✅ Added media_mime_type column\n";
    } else {
        echo "⚠️  media_mime_type column already exists\n";
    }
    
    // Add media_caption column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_caption'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_caption VARCHAR(255) NULL AFTER media_mime_type");
        echo "✅ Added media_caption column\n";
    } else {
        echo "⚠️  media_caption column already exists\n";
    }
    
    // Add media_id column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_id VARCHAR(255) NULL AFTER media_caption");
        echo "✅ Added media_id column\n";
    } else {
        echo "⚠️  media_id column already exists\n";
    }
    
    // Add media_filename column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_filename'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_filename VARCHAR(255) NULL AFTER media_id");
        echo "✅ Added media_filename column\n";
    } else {
        echo "⚠️  media_filename column already exists\n";
    }
    
    // Add media_size column if missing
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'media_size'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN media_size BIGINT NULL AFTER media_filename");
        echo "✅ Added media_size column\n";
    } else {
        echo "⚠️  media_size column already exists\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    http_response_code(500);
}
?>
