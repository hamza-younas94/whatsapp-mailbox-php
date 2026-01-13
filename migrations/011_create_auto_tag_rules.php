<?php
/**
 * Migration: Create auto tag rules table
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

if (!$schema->hasTable('auto_tag_rules')) {
    $schema->create('auto_tag_rules', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('keywords'); // JSON array of keywords
        $table->unsignedBigInteger('tag_id');
        $table->boolean('is_active')->default(true);
        $table->boolean('case_sensitive')->default(false);
        $table->enum('match_type', ['any', 'all', 'exact'])->default('any');
        $table->integer('priority')->default(0);
        $table->integer('usage_count')->default(0);
        $table->timestamps();
        
        $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
    });
    
    echo "✅ Created auto_tag_rules table\n";
} else {
    echo "⚠️  auto_tag_rules table already exists\n";
}

return [
    'up' => function() {
        echo "Auto tag rules migration completed\n";
    },
    'down' => function() {
        Capsule::schema()->dropIfExists('auto_tag_rules');
        echo "Auto tag rules table dropped\n";
    }
];
