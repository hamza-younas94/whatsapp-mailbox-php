<?php
/**
 * Migration: Create broadcast messaging system
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Broadcasts table
        $schema->create('broadcasts', function ($table) {
            $table->id();
            $table->string('name', 150);
            $table->text('message');
            $table->string('message_type')->default('text'); // text, template
            $table->string('template_name')->nullable();
            $table->datetime('scheduled_at')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sending, completed, failed
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('read_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->timestamps();
        });
        
        // Broadcast recipients table
        $schema->create('broadcast_recipients', function ($table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, sent, failed, delivered, read
            $table->string('whatsapp_message_id')->nullable();
            $table->datetime('sent_at')->nullable();
            $table->datetime('delivered_at')->nullable();
            $table->datetime('read_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['broadcast_id', 'status']);
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('broadcast_recipients');
        Capsule::schema()->dropIfExists('broadcasts');
    }
];
