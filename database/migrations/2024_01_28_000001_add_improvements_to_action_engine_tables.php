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
        // Add indexes to bulk_action_executions table
        Schema::table('bulk_action_executions', function (Blueprint $table) {
            // Performance indexes
            $table->index('status', 'idx_executions_status');
            $table->index('created_by', 'idx_executions_created_by');
            $table->index(['action_type', 'status'], 'idx_executions_action_status');
            $table->index('created_at', 'idx_executions_created_at');
            $table->index(['status', 'created_at'], 'idx_executions_status_created');
            
            // Composite index for common queries
            $table->index(['created_by', 'action_type', 'created_at'], 
                'idx_executions_user_action_date');
            
            // Add new columns for safety features
            $table->boolean('is_locked')->default(false)->after('is_dry_run');
            $table->timestamp('rolled_back_at')->nullable()->after('completed_at');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('failed_records');
        });

        // Add indexes to bulk_action_progress table
        Schema::table('bulk_action_progress', function (Blueprint $table) {
            // Foreign key optimization
            $table->index('execution_id', 'idx_progress_execution');
            $table->index(['execution_id', 'status'], 'idx_progress_execution_status');
            $table->index(['execution_id', 'record_id'], 'idx_progress_execution_record');
            
            // For finding failed records
            $table->index(['execution_id', 'status', 'processed_at'], 
                'idx_progress_status_processed');
        });

        // Add indexes to bulk_action_undo table
        Schema::table('bulk_action_undo', function (Blueprint $table) {
            // Foreign key and lookup optimization
            $table->index('execution_id', 'idx_undo_execution');
            $table->index(['execution_id', 'record_id'], 'idx_undo_execution_record');
            $table->index(['model_type', 'record_id'], 'idx_undo_model_record');
            
            // For cleanup queries
            $table->index('created_at', 'idx_undo_created_at');
            
            // Add compression flag
            $table->boolean('is_compressed')->default(false)->after('original_data');
        });

        // Add indexes to bulk_action_audit table
        Schema::table('bulk_action_audit', function (Blueprint $table) {
            // Query optimization
            $table->index('execution_id', 'idx_audit_execution');
            $table->index('user_id', 'idx_audit_user');
            $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            $table->index('created_at', 'idx_audit_created_at');
            
            // For compliance queries
            $table->index(['action', 'created_at'], 'idx_audit_action_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_action_executions', function (Blueprint $table) {
            $table->dropIndex('idx_executions_status');
            $table->dropIndex('idx_executions_created_by');
            $table->dropIndex('idx_executions_action_status');
            $table->dropIndex('idx_executions_created_at');
            $table->dropIndex('idx_executions_status_created');
            $table->dropIndex('idx_executions_user_action_date');
            
            $table->dropColumn(['is_locked', 'rolled_back_at', 'retry_count']);
        });

        Schema::table('bulk_action_progress', function (Blueprint $table) {
            $table->dropIndex('idx_progress_execution');
            $table->dropIndex('idx_progress_execution_status');
            $table->dropIndex('idx_progress_execution_record');
            $table->dropIndex('idx_progress_status_processed');
        });

        Schema::table('bulk_action_undo', function (Blueprint $table) {
            $table->dropIndex('idx_undo_execution');
            $table->dropIndex('idx_undo_execution_record');
            $table->dropIndex('idx_undo_model_record');
            $table->dropIndex('idx_undo_created_at');
            
            $table->dropColumn('is_compressed');
        });

        Schema::table('bulk_action_audit', function (Blueprint $table) {
            $table->dropIndex('idx_audit_execution');
            $table->dropIndex('idx_audit_user');
            $table->dropIndex('idx_audit_user_date');
            $table->dropIndex('idx_audit_created_at');
            $table->dropIndex('idx_audit_action_date');
        });
    }
};
