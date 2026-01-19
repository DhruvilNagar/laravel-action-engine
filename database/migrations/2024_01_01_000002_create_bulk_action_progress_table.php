<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('action-engine.tables.progress', 'bulk_action_progress'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_action_execution_id')
                ->constrained(config('action-engine.tables.executions', 'bulk_action_executions'))
                ->cascadeOnDelete();
            $table->unsignedInteger('batch_number');
            $table->unsignedInteger('batch_size')->default(0);
            $table->unsignedInteger('total_in_batch')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed'
            ])->default('pending');
            $table->json('affected_ids')->nullable();
            $table->json('failed_ids')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['bulk_action_execution_id', 'status']);
            $table->index('batch_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('action-engine.tables.progress', 'bulk_action_progress'));
    }
};
