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
        Schema::create(config('action-engine.tables.undo', 'bulk_action_undo'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_action_execution_id')
                ->constrained(config('action-engine.tables.executions', 'bulk_action_executions'))
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->json('original_data')->nullable();
            $table->json('changes')->nullable();
            $table->enum('undo_action_type', [
                'restore',
                'delete',
                'update',
                'recreate'
            ]);
            $table->boolean('undone')->default(false);
            $table->timestamp('undone_at')->nullable();
            $table->unsignedBigInteger('undone_by')->nullable();
            $table->timestamps();

            $table->index(['bulk_action_execution_id', 'undone']);
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('action-engine.tables.undo', 'bulk_action_undo'));
    }
};
