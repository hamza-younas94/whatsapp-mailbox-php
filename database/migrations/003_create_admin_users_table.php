<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create admin_users table
 */
return new class {
    public function up()
    {
        Capsule::schema()->create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password_hash', 255);
            $table->string('email', 255)->nullable();
            $table->dateTime('last_login')->nullable();
            $table->timestamps();
        });
        
        // Insert default admin user (password: admin123)
        Capsule::table('admin_users')->insert([
            'username' => 'admin',
            'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'email' => 'admin@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('admin_users');
    }
};
