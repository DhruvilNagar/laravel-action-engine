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
        Schema::create(config('action-engine.tables.audit', 'bulk_action_audit'), function (Blueprint $table) {
            $table->id();
            $table->uuid('execution_uuid');
            $table->string('action_name');
            $table->string('model_type');
            $table->json('filters')->nullable();
            $table->json('parameters')->nullable();
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->string('status');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_type')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('affected_ids')->nullable();
            $table->boolean('was_undone')->default(false);
            $table->timestamp('undone_at')->nullable();
            $table->unsignedBigInteger('undone_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('execution_uuid');
            $table->index(['user_id', 'user_type']);
            $table->index('action_name');
            $table->index('model_type');
            $table->index('created_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('action-engine.tables.audit', 'bulk_action_audit'));
    }
};
