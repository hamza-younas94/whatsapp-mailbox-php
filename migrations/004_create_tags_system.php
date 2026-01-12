<?php
/**
 * Migration: Create tags system for contacts
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Tags table
        $schema->create('tags', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('color', 7)->default('#25D366'); // Hex color
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Contact tags pivot table
        $schema->create('contact_tag', function ($table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained()->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['contact_id', 'tag_id']);
        });
        
        // Insert default tags
        $defaultTags = [
            ['name' => 'VIP', 'color' => '#f59e0b', 'description' => 'Very Important Person'],
            ['name' => 'Hot Lead', 'color' => '#ef4444', 'description' => 'High priority lead'],
            ['name' => 'Follow Up', 'color' => '#3b82f6', 'description' => 'Needs follow up'],
            ['name' => 'Interested', 'color' => '#10b981', 'description' => 'Showed interest'],
            ['name' => 'Not Interested', 'color' => '#6b7280', 'description' => 'Not interested'],
        ];
        
        foreach ($defaultTags as $tag) {
            $tag['created_at'] = $tag['updated_at'] = now();
            Capsule::table('tags')->insert($tag);
        }
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('contact_tag');
        Capsule::schema()->dropIfExists('tags');
    }
];
