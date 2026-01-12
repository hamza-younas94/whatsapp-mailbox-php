<?php
/**
 * Migration: Add Deals/Transactions table for recurring purchases
 */

require_once __DIR__ . '/../bootstrap.php';

use Illuminate\Database\Capsule\Manager as DB;

echo "Creating deals table for tracking recurring purchases...\n";

try {
    DB::schema()->create('deals', function ($table) {
        $table->id();
        $table->unsignedBigInteger('contact_id');
        $table->string('deal_name')->nullable();
        $table->text('description')->nullable();
        $table->decimal('amount', 10, 2)->default(0);
        $table->string('currency', 3)->default('PKR');
        $table->enum('status', ['pending', 'won', 'lost', 'cancelled'])->default('pending');
        $table->date('deal_date')->nullable();
        $table->date('expected_close_date')->nullable();
        $table->date('actual_close_date')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
        
        $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
        $table->index('contact_id');
        $table->index('status');
        $table->index('deal_date');
    });
    
    echo "✅ Deals table created successfully!\n";
    echo "This table will track:\n";
    echo "  - Multiple purchases from same customer\n";
    echo "  - Deal history and value over time\n";
    echo "  - Customer lifetime value\n";
    echo "  - Win/loss tracking\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
