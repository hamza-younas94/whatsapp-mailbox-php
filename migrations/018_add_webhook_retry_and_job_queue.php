<?php
/**
 * Migration 018: Webhook retries + durable job queue
 */

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function () {
        // Add retry columns to webhook_deliveries (best-effort if table exists)
        if (Capsule::schema()->hasTable('webhook_deliveries')) {
            try {
                if (!Capsule::schema()->hasColumn('webhook_deliveries', 'retry_count')) {
                    Capsule::schema()->table('webhook_deliveries', function ($table) {
                        $table->integer('retry_count')->default(0);
                        $table->dateTime('next_retry_at')->nullable();
                        $table->text('last_error')->nullable();
                    });
                    echo "✅ Added retry_count/next_retry_at to webhook_deliveries\n";
                }
            } catch (\Exception $e) {
                echo "⚠️  Could not alter webhook_deliveries: " . $e->getMessage() . "\n";
            }
        }

        // Durable job queue
        if (!Capsule::schema()->hasTable('job_queue')) {
            Capsule::schema()->create('job_queue', function ($table) {
                $table->increments('id');
                $table->integer('user_id')->index();
                $table->string('type', 100); // scheduled_message, broadcast_recipient, webhook_delivery
                $table->integer('reference_id')->index();
                $table->longText('payload')->nullable();
                $table->enum('status', ['pending', 'reserved', 'completed', 'failed'])->default('pending');
                $table->integer('attempts')->default(0);
                $table->string('last_error')->nullable();
                $table->dateTime('available_at')->index();
                $table->dateTime('reserved_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['type', 'reference_id'], 'uniq_job_ref');
            });
            echo "✅ Created job_queue table\n";
        } else {
            echo "✅ job_queue table already exists\n";
        }

        // Audit logs table
        if (!Capsule::schema()->hasTable('audit_logs')) {
            Capsule::schema()->create('audit_logs', function ($table) {
                $table->increments('id');
                $table->integer('user_id')->nullable()->index();
                $table->string('action', 100);
                $table->string('entity_type', 100);
                $table->integer('entity_id')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
            echo "✅ Created audit_logs table\n";
        } else {
            echo "✅ audit_logs table already exists\n";
        }
    },

    'down' => function () {
        if (Capsule::schema()->hasTable('audit_logs')) {
            Capsule::schema()->drop('audit_logs');
            echo "✅ Dropped audit_logs table\n";
        }
        if (Capsule::schema()->hasTable('job_queue')) {
            Capsule::schema()->drop('job_queue');
            echo "✅ Dropped job_queue table\n";
        }
        if (Capsule::schema()->hasTable('webhook_deliveries') && Capsule::schema()->hasColumn('webhook_deliveries', 'retry_count')) {
            Capsule::schema()->table('webhook_deliveries', function ($table) {
                $table->dropColumn(['retry_count', 'next_retry_at', 'last_error']);
            });
            echo "✅ Removed retry columns from webhook_deliveries\n";
        }
    },
];
