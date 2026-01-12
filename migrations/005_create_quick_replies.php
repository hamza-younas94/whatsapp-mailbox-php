<?php
/**
 * Migration: Create quick replies / canned responses
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        $schema->create('quick_replies', function ($table) {
            $table->id();
            $table->string('shortcut', 50); // e.g., /hello, /pricing
            $table->string('title', 100);
            $table->text('message');
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->timestamps();
            
            $table->unique('shortcut');
        });
        
        // Insert default quick replies
        $defaultReplies = [
            [
                'shortcut' => '/hello',
                'title' => 'Welcome Message',
                'message' => 'Hello! Thank you for contacting us. How can I help you today?',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'shortcut' => '/hours',
                'title' => 'Business Hours',
                'message' => 'Our business hours are:\nMonday - Friday: 9:00 AM - 6:00 PM\nSaturday: 10:00 AM - 4:00 PM\nSunday: Closed',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'shortcut' => '/thanks',
                'title' => 'Thank You',
                'message' => 'Thank you for your inquiry! We will get back to you shortly.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        
        foreach ($defaultReplies as $reply) {
            Capsule::table('quick_replies')->insert($reply);
        }
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('quick_replies');
    }
];
