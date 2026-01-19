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
        Schema::create(config('action-engine.tables.executions', 'bulk_action_executions'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('action_name');
            $table->string('model_type');
            $table->json('filters')->nullable();
            $table->json('parameters')->nullable();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->enum('status', [
                'pending',
                'scheduled',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'partially_completed'
            ])->default('pending');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('scheduled_timezone')->nullable();
            $table->json('error_details')->nullable();
            $table->boolean('can_undo')->default(false);
            $table->timestamp('undo_expires_at')->nullable();
            $table->boolean('is_dry_run')->default(false);
            $table->json('dry_run_results')->nullable();
            $table->unsignedInteger('batch_size')->nullable();
            $table->string('queue_connection')->nullable();
            $table->string('queue_name')->nullable();
            $table->json('callbacks')->nullable();
            $table->json('chain_config')->nullable();
            $table->uuid('parent_execution_uuid')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'user_type']);
            $table->index('scheduled_for');
            $table->index('model_type');
            $table->index('action_name');
            $table->index('parent_execution_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('action-engine.tables.executions', 'bulk_action_executions'));
    }
};
