<?php
/**
 * Migration: Create automated workflows
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        $schema->create('workflows', function ($table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('trigger_type'); // new_message, stage_change, tag_added, time_based, lead_score_change
            $table->json('trigger_conditions');
            $table->json('actions'); // Array of actions to perform
            $table->boolean('is_active')->default(true);
            $table->integer('execution_count')->default(0);
            $table->datetime('last_executed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admin_users')->onDelete('set null');
            $table->timestamps();
        });
        
        // Workflow execution log
        $schema->create('workflow_executions', function ($table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('status')->default('success'); // success, failed
            $table->json('actions_performed');
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->useCurrent();
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('workflow_executions');
        Capsule::schema()->dropIfExists('workflows');
    }
];
