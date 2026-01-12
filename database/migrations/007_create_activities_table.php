<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Create activities table for activity timeline
Capsule::schema()->create('activities', function ($table) {
    $table->id();
    $table->unsignedBigInteger('contact_id');
    $table->string('type', 50); // message_sent, message_received, stage_changed, note_added, etc.
    $table->string('title');
    $table->text('description')->nullable();
    $table->json('metadata')->nullable(); // Store additional data
    $table->unsignedBigInteger('created_by')->nullable();
    $table->timestamps();
    
    $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
    $table->index('contact_id');
    $table->index('type');
    $table->index('created_at');
});

echo "Created activities table\n";
