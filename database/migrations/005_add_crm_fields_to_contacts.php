<?php

use Illuminate\Database\Capsule\Manager as Capsule;

// Add CRM fields to contacts table
Capsule::schema()->table('contacts', function ($table) {
    // CRM Stage
    $table->string('stage', 50)->default('new')->after('unread_count');
    // Stages: new, contacted, qualified, proposal, negotiation, customer, lost
    
    // Lead Score (0-100)
    $table->integer('lead_score')->default(0)->after('stage');
    
    // Assignment
    $table->unsignedBigInteger('assigned_to')->nullable()->after('lead_score');
    
    // Source
    $table->string('source', 100)->nullable()->after('assigned_to');
    // Sources: whatsapp, website, referral, campaign, etc.
    
    // Company Info
    $table->string('company_name')->nullable()->after('source');
    $table->string('email')->nullable()->after('company_name');
    $table->string('city')->nullable()->after('email');
    $table->string('country', 50)->default('Pakistan')->after('city');
    
    // Tags (comma-separated)
    $table->text('tags')->nullable()->after('country');
    
    // Last Activity
    $table->timestamp('last_activity_at')->nullable()->after('tags');
    $table->string('last_activity_type', 50)->nullable()->after('last_activity_at');
    // Types: message_sent, message_received, call, meeting, note
    
    // Deal Info
    $table->decimal('deal_value', 10, 2)->nullable()->after('last_activity_type');
    $table->string('deal_currency', 10)->default('PKR')->after('deal_value');
    $table->date('expected_close_date')->nullable()->after('deal_currency');
    
    // Custom Fields (JSON)
    $table->json('custom_fields')->nullable()->after('expected_close_date');
    
    // Indexes
    $table->index('stage');
    $table->index('assigned_to');
    $table->index('lead_score');
    $table->index('last_activity_at');
});

echo "Added CRM fields to contacts table\n";
