<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create contacts table
 */
return new class {
    public function up()
    {
        Capsule::schema()->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->unique();
            $table->string('name', 255)->nullable();
            $table->text('profile_picture_url')->nullable();
            $table->dateTime('last_message_time')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();
            
            $table->index('phone_number');
            $table->index('last_message_time');
        });
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('contacts');
    }
};
