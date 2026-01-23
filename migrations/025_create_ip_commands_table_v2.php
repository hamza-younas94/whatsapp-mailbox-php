<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create ip_commands table (v2) to ensure migration runs
 */
return [
    'up' => function() {
        if (!Capsule::schema()->hasTable('ip_commands')) {
            Capsule::schema()->create('ip_commands', function (Blueprint $table) {
                $table->id();
                $table->string('ip_address', 100);
                $table->string('contact_name', 255)->nullable();
                $table->string('phone_number', 20)->nullable();
                $table->text('api_response')->nullable();
                $table->integer('http_code')->nullable();
                $table->enum('status', ['success', 'failed'])->default('success');
                $table->timestamps();
                
                $table->index('ip_address');
                $table->index('created_at');
            });
            
            echo "✅ Created ip_commands table\n";
        } else {
            echo "ℹ️  ip_commands table already exists\n";
        }
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('ip_commands');
        echo "✅ Dropped ip_commands table\n";
    }
];
