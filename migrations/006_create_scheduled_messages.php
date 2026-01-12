<?php
/**
 * Migration: Create scheduled messages
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        $schema->create('scheduled_messages', function ($table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->text('message');
            $table->string('message_type')->default('text'); // text, template
            $table->string('template_name')->nullable();
            $table->datetime('scheduled_at');
            $table->datetime('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, cancelled
            $table->string('whatsapp_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable(); // daily, weekly, monthly
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['status', 'scheduled_at']);
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('scheduled_messages');
    }
];
