<?php
/**
 * Database Seeder - Creates default admin user
 */

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

echo "Starting database seeding...\n\n";
flush();

try {
    require_once __DIR__ . '/../bootstrap.php';
    echo "Bootstrap loaded successfully.\n";
    flush();
} catch (Exception $e) {
    echo "ERROR loading bootstrap: " . $e->getMessage() . "\n";
    exit(1);
}

use App\Models\AdminUser;

echo "\nSeeding admin_users table...\n";
flush();

try {
    // Check if admin user already exists
    $existingAdmin = AdminUser::where('username', 'admin')->first();
    
    if ($existingAdmin) {
        echo "✓ Admin user already exists (username: admin)\n";
        echo "  To reset password, delete the user first.\n\n";
    } else {
        // Create default admin user
        $admin = AdminUser::create([
            'username' => 'admin',
            'password' => 'admin123', // Will be hashed by model mutator
            'email' => 'admin@whatsapp-mailbox.local'
        ]);
        
        echo "✓ Default admin user created successfully!\n";
        echo "  Username: admin\n";
        echo "  Password: admin123\n";
        echo "  ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!\n\n";
    }
    
    echo "Seeding completed successfully!\n";
} catch (Exception $e) {
    echo "✗ Seeding failed\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
