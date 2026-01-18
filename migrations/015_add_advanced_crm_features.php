<?php
/**
 * Migration: Add Advanced CRM and Mailbox Features
 * - Tasks/Reminders
 * - Message Actions (star, forward)
 * - Conversation Priority/Status
 * - Contact Merge History
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function() {
        $schema = Capsule::schema();
        
        // Tasks/Reminders table
        if (!$schema->hasTable('tasks')) {
            $schema->create('tasks', function ($table) {
                $table->id();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->enum('type', ['call', 'meeting', 'follow_up', 'email', 'other'])->default('follow_up');
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
                $table->datetime('due_date')->nullable();
                $table->datetime('completed_at')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
                $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index('contact_id');
                $table->index('status');
                $table->index('due_date');
                $table->index('assigned_to');
            });
        }
        
        // Message actions table (star, forward, etc.)
        if (!$schema->hasTable('message_actions')) {
            $schema->create('message_actions', function ($table) {
                $table->id();
                $table->unsignedBigInteger('message_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('action_type', ['star', 'forward', 'delete', 'archive'])->default('star');
                $table->unsignedBigInteger('forwarded_to_contact_id')->nullable(); // For forward action
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->foreign('message_id')->references('id')->on('messages')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('forwarded_to_contact_id')->references('id')->on('contacts')->onDelete('set null');
                $table->unique(['message_id', 'user_id', 'action_type'], 'unique_message_user_action');
                $table->index('message_id');
                $table->index('user_id');
            });
        }
        
        // Conversation status/priority (add to contacts table if needed)
        if (!$schema->hasColumn('contacts', 'conversation_status')) {
            $schema->table('contacts', function ($table) {
                $table->enum('conversation_status', ['open', 'pending', 'resolved', 'closed'])->default('open')->after('last_activity_at');
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('conversation_status');
                $table->boolean('is_starred')->default(false)->after('priority');
                $table->boolean('is_archived')->default(false)->after('is_starred');
            });
        }
        
        // Contact merge history
        if (!$schema->hasTable('contact_merges')) {
            $schema->create('contact_merges', function ($table) {
                $table->id();
                $table->unsignedBigInteger('source_contact_id'); // Contact that was merged into
                $table->unsignedBigInteger('target_contact_id'); // Contact that kept data
                $table->unsignedBigInteger('merged_by');
                $table->text('merge_reason')->nullable();
                $table->json('merged_data')->nullable(); // What data was merged
                $table->timestamps();
                
                $table->foreign('source_contact_id')->references('id')->on('contacts')->onDelete('cascade');
                $table->foreign('target_contact_id')->references('id')->on('contacts')->onDelete('cascade');
                $table->foreign('merged_by')->references('id')->on('users')->onDelete('cascade');
                $table->index('source_contact_id');
                $table->index('target_contact_id');
            });
        }
        
        // Message search index (fulltext search support)
        // Note: MySQL fulltext indexes require MyISAM or InnoDB with innodb_ft_min_token_size set
        // We'll add a regular index and handle fulltext in application layer
        
        echo "✅ Created tasks table\n";
        echo "✅ Created message_actions table\n";
        echo "✅ Added conversation status, priority, starred, archived to contacts\n";
        echo "✅ Created contact_merges table\n";
    },
    
    'down' => function() {
        $schema = Capsule::schema();
        
        $schema->dropIfExists('contact_merges');
        $schema->dropIfExists('message_actions');
        $schema->dropIfExists('tasks');
        
        if ($schema->hasColumn('contacts', 'conversation_status')) {
            $schema->table('contacts', function ($table) {
                $table->dropColumn(['conversation_status', 'priority', 'is_starred', 'is_archived']);
            });
        }
        
        echo "✅ Rolled back advanced CRM features\n";
    }
];

