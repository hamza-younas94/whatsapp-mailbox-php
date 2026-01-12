<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Create notes table for CRM
Capsule::schema()->create('notes', function ($table) {
    $table->id();
    $table->unsignedBigInteger('contact_id');
    $table->unsignedBigInteger('created_by')->nullable(); // admin user id
    $table->text('content');
    $table->string('type', 50)->default('general'); // general, call, meeting, email
    $table->timestamps();
    
    $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
    $table->index('contact_id');
    $table->index('created_by');
    $table->index('created_at');
});

echo "Created notes table\n";
