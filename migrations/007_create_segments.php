<?php
/**
 * Migration: Create contact segments for targeting
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        $schema->create('segments', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('conditions'); // Stored as JSON for flexibility
            $table->boolean('is_dynamic')->default(true); // Auto-update based on conditions
            $table->integer('contact_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->timestamps();
        });
        
        // Insert default segments
        $defaultSegments = [
            [
                'name' => 'High Value Customers',
                'description' => 'Customers with total revenue > PKR 10,000',
                'conditions' => json_encode(['total_revenue' => ['operator' => '>', 'value' => 10000]]),
                'is_dynamic' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Hot Leads',
                'description' => 'Contacts in proposal or negotiation stage',
                'conditions' => json_encode(['stage' => ['operator' => 'in', 'value' => ['proposal', 'negotiation']]]),
                'is_dynamic' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Inactive Contacts',
                'description' => 'No messages in last 30 days',
                'conditions' => json_encode(['last_message_days' => ['operator' => '>', 'value' => 30]]),
                'is_dynamic' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        
        foreach ($defaultSegments as $segment) {
            Capsule::table('segments')->insert($segment);
        }
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('segments');
    }
];
