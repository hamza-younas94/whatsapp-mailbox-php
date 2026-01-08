<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

/**
 * Create messages table
 */
return new class {
    public function up()
    {
        Capsule::schema()->create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->unique()->nullable();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('phone_number', 20);
            $table->enum('message_type', ['text', 'image', 'audio', 'video', 'document', 'location', 'template'])->default('text');
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->text('message_body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('media_mime_type', 100)->nullable();
            $table->text('media_caption')->nullable();
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->boolean('is_read')->default(false);
            $table->dateTime('timestamp');
            $table->timestamps();
            
            $table->index('contact_id');
            $table->index('timestamp');
            $table->index(['is_read', 'timestamp']);
        });
    }

    public function down()
    {
        Capsule::schema()->dropIfExists('messages');
    }
};
