<?php
/**
 * Migration: Create internal notes, templates, and drip campaigns tables
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

// Internal notes (team-only, not visible to customer)
if (!$schema->hasTable('internal_notes')) {
    $schema->create('internal_notes', function ($table) {
        $table->id();
        $table->unsignedBigInteger('contact_id');
        $table->unsignedBigInteger('created_by');
        $table->text('content');
        $table->boolean('is_pinned')->default(false);
        $table->timestamps();
        
        $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        $table->index(['contact_id', 'created_at']);
    });
    echo "✅ Created internal_notes table\n";
}

// Message templates library
if (!$schema->hasTable('message_templates')) {
    $schema->create('message_templates', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('whatsapp_template_name')->unique();
        $table->string('language_code')->default('en');
        $table->text('content');
        $table->json('variables')->nullable(); // {{1}}, {{2}} placeholders
        $table->string('category')->default('utility'); // marketing, utility, authentication
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->integer('usage_count')->default(0);
        $table->unsignedBigInteger('created_by');
        $table->timestamps();
        
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    });
    echo "✅ Created message_templates table\n";
}

// Drip campaigns
if (!$schema->hasTable('drip_campaigns')) {
    $schema->create('drip_campaigns', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->boolean('is_active')->default(false);
        $table->json('trigger_conditions'); // segment_id, tags, stage
        $table->integer('total_subscribers')->default(0);
        $table->integer('completed_count')->default(0);
        $table->unsignedBigInteger('created_by');
        $table->timestamps();
        
        $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    });
    echo "✅ Created drip_campaigns table\n";
}

// Drip campaign steps
if (!$schema->hasTable('drip_campaign_steps')) {
    $schema->create('drip_campaign_steps', function ($table) {
        $table->id();
        $table->unsignedBigInteger('campaign_id');
        $table->integer('step_number');
        $table->string('name');
        $table->integer('delay_minutes'); // wait time before this step
        $table->enum('message_type', ['text', 'template'])->default('text');
        $table->text('message_content');
        $table->unsignedBigInteger('template_id')->nullable();
        $table->integer('sent_count')->default(0);
        $table->timestamps();
        
        $table->foreign('campaign_id')->references('id')->on('drip_campaigns')->onDelete('cascade');
        $table->foreign('template_id')->references('id')->on('message_templates')->onDelete('set null');
        $table->unique(['campaign_id', 'step_number']);
    });
    echo "✅ Created drip_campaign_steps table\n";
}

// Drip campaign subscribers
if (!$schema->hasTable('drip_subscribers')) {
    $schema->create('drip_subscribers', function ($table) {
        $table->id();
        $table->unsignedBigInteger('campaign_id');
        $table->unsignedBigInteger('contact_id');
        $table->integer('current_step')->default(0);
        $table->enum('status', ['active', 'completed', 'paused', 'unsubscribed'])->default('active');
        $table->timestamp('next_send_at')->nullable();
        $table->timestamp('started_at');
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
        
        $table->foreign('campaign_id')->references('id')->on('drip_campaigns')->onDelete('cascade');
        $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        $table->unique(['campaign_id', 'contact_id']);
        $table->index('next_send_at');
    });
    echo "✅ Created drip_subscribers table\n";
}

// Webhooks configuration
if (!$schema->hasTable('webhooks')) {
    $schema->create('webhooks', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('url');
        $table->json('events'); // message.received, contact.created, etc.
        $table->string('secret')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('success_count')->default(0);
        $table->integer('failure_count')->default(0);
        $table->timestamp('last_triggered_at')->nullable();
        $table->timestamps();
    });
    echo "✅ Created webhooks table\n";
}

// Response time tracking
if (!$schema->hasColumn('messages', 'response_time_minutes')) {
    $schema->table('messages', function ($table) {
        $table->decimal('response_time_minutes', 8, 2)->nullable()->after('is_read');
        $table->unsignedBigInteger('responded_by')->nullable()->after('response_time_minutes');
        $table->foreign('responded_by')->references('id')->on('users')->onDelete('set null');
    });
    echo "✅ Added response time tracking to messages\n";
}

return [
    'up' => function() {
        echo "Phase 2 & 3 migrations completed\n";
    },
    'down' => function() {
        $schema = Capsule::schema();
        $schema->dropIfExists('drip_subscribers');
        $schema->dropIfExists('drip_campaign_steps');
        $schema->dropIfExists('drip_campaigns');
        $schema->dropIfExists('message_templates');
        $schema->dropIfExists('internal_notes');
        $schema->dropIfExists('webhooks');
        $schema->table('messages', function ($table) {
            $table->dropForeign(['responded_by']);
            $table->dropColumn(['response_time_minutes', 'responded_by']);
        });
        echo "Phase 2 & 3 tables dropped\n";
    }
];
