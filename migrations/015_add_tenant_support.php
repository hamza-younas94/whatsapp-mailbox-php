<?php
/**
 * Migration: Add tenant/user_id columns to all tables for multi-tenant support
 * This enables data isolation where each user only sees their own data
 */

use Illuminate\Database\Capsule\Manager as Capsule;

$schema = Capsule::schema();

// Get or create a default admin user for existing data
$defaultUser = Capsule::table('users')->first();
if (!$defaultUser) {
    // Create a default admin user if none exists
    $defaultUserId = Capsule::table('users')->insertGetId([
        'name' => 'Default Admin',
        'email' => 'admin@example.com',
        'password' => password_hash('changeme123', PASSWORD_DEFAULT),
        'role' => 'admin',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    echo "✅ Created default admin user (ID: $defaultUserId)\n";
} else {
    $defaultUserId = $defaultUser->id;
    echo "✅ Using existing user (ID: $defaultUserId) for existing data\n";
}

// 1. Create user_settings table for per-user API credentials
if (!$schema->hasTable('user_settings')) {
    $schema->create('user_settings', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('whatsapp_api_version')->default('v18.0');
        $table->string('whatsapp_access_token')->nullable();
        $table->string('whatsapp_phone_number_id')->nullable();
        $table->string('phone_number')->nullable();
        $table->string('business_name')->nullable();
        $table->string('webhook_verify_token')->nullable();
        $table->string('webhook_url')->nullable();
        $table->boolean('is_configured')->default(false);
        $table->timestamp('last_verified_at')->nullable();
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->unique('user_id');
    });
    echo "✅ Created user_settings table\n";
}

// 2. Add user_id to contacts
if (!$schema->hasColumn('contacts', 'user_id')) {
    // Step 1: Add column as nullable
    $schema->table('contacts', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    
    // Step 2: Populate with default user
    Capsule::table('contacts')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    
    // Step 3: Make NOT NULL and add foreign key
    $schema->table('contacts', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to contacts\n";
}

// 3. Add user_id to messages
if (!$schema->hasColumn('messages', 'user_id')) {
    // Step 1: Add column as nullable
    $schema->table('messages', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    
    // Step 2: Populate with default user
    Capsule::table('messages')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    
    // Step 3: Make NOT NULL and add foreign key
    $schema->table('messages', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to messages\n";
}

// 4. Add user_id to quick_replies
if (!$schema->hasColumn('quick_replies', 'user_id')) {
    $schema->table('quick_replies', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('quick_replies')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('quick_replies', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to quick_replies\n";
}

// 5. Add user_id to broadcasts
if (!$schema->hasColumn('broadcasts', 'user_id')) {
    $schema->table('broadcasts', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('broadcasts')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('broadcasts', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to broadcasts\n";
}

// 6. Add user_id to scheduled_messages
if (!$schema->hasColumn('scheduled_messages', 'user_id')) {
    $schema->table('scheduled_messages', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('scheduled_messages')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('scheduled_messages', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to scheduled_messages\n";
}

// 7. Add user_id to segments
if (!$schema->hasColumn('segments', 'user_id')) {
    $schema->table('segments', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('segments')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('segments', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to segments\n";
}

// 8. Add user_id to tags
if (!$schema->hasColumn('tags', 'user_id')) {
    $schema->table('tags', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('tags')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('tags', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to tags\n";
}

// 9. Add user_id to auto_tag_rules
if (!$schema->hasColumn('auto_tag_rules', 'user_id')) {
    $schema->table('auto_tag_rules', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('auto_tag_rules')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('auto_tag_rules', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to auto_tag_rules\n";
}

// 10. Add user_id to deals
if (!$schema->hasColumn('deals', 'user_id')) {
    $schema->table('deals', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('deals')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('deals', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to deals\n";
}

// 11. Add user_id to workflows
if (!$schema->hasColumn('workflows', 'user_id')) {
    $schema->table('workflows', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('workflows')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('workflows', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to workflows\n";
}

// 12. Add user_id to internal_notes
if (!$schema->hasColumn('internal_notes', 'user_id')) {
    $schema->table('internal_notes', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('internal_notes')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('internal_notes', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to internal_notes\n";
}

// 13. Add user_id to ip_commands
if (!$schema->hasColumn('ip_commands', 'user_id')) {
    $schema->table('ip_commands', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('id');
    });
    Capsule::table('ip_commands')->whereNull('user_id')->update(['user_id' => $defaultUserId]);
    $schema->table('ip_commands', function ($table) {
        $table->unsignedBigInteger('user_id')->nullable(false)->change();
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->index('user_id');
    });
    echo "✅ Added user_id to ip_commands\n";
}

echo "\n✅ All tenant support migrations completed!\n";
