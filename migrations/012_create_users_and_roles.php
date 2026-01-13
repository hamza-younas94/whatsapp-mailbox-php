<?php
/**
 * Migration: Create users and roles tables for multi-user support
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

// Create users table (enhanced admin_users)
if (!$schema->hasTable('users')) {
    $schema->create('users', function ($table) {
        $table->id();
        $table->string('username')->unique();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('full_name');
        $table->enum('role', ['admin', 'agent', 'viewer'])->default('agent');
        $table->boolean('is_active')->default(true);
        $table->string('avatar_url')->nullable();
        $table->string('phone')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->integer('messages_sent')->default(0);
        $table->integer('conversations_handled')->default(0);
        $table->decimal('avg_response_time', 8, 2)->nullable(); // in minutes
        $table->timestamps();
    });
    
    echo "✅ Created users table\n";
    
    // Migrate existing admin_users if exists
    if ($schema->hasTable('admin_users')) {
        $admins = Capsule::table('admin_users')->get();
        foreach ($admins as $admin) {
            Capsule::table('users')->insert([
                'id' => $admin->id,
                'username' => $admin->username,
                'email' => $admin->email ?? $admin->username . '@example.com',
                'password' => $admin->password,
                'full_name' => $admin->username,
                'role' => 'admin',
                'is_active' => true,
                'created_at' => $admin->created_at ?? now(),
                'updated_at' => $admin->updated_at ?? now()
            ]);
        }
        echo "✅ Migrated " . count($admins) . " admin users\n";
    }
} else {
    echo "⚠️  users table already exists\n";
}

// Add assigned_agent_id to contacts
if (!$schema->hasColumn('contacts', 'assigned_agent_id')) {
    $schema->table('contacts', function ($table) {
        $table->unsignedBigInteger('assigned_agent_id')->nullable()->after('assigned_to');
        $table->foreign('assigned_agent_id')->references('id')->on('users')->onDelete('set null');
    });
    echo "✅ Added assigned_agent_id to contacts\n";
}

return [
    'up' => function() {
        echo "Multi-user system migration completed\n";
    },
    'down' => function() {
        $schema = Capsule::schema();
        $schema->table('contacts', function ($table) {
            $table->dropForeign(['assigned_agent_id']);
            $table->dropColumn('assigned_agent_id');
        });
        $schema->dropIfExists('users');
        echo "Multi-user tables dropped\n";
    }
];
