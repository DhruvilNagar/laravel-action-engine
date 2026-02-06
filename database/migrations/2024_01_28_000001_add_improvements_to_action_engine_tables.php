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
        $connection = Schema::connection(config('action-engine.database_connection'));
        
        // Add indexes to bulk_action_executions table
        $connection->table(config('action-engine.tables.executions', 'bulk_action_executions'), function (Blueprint $table) {
            // Add new columns for safety features first
            if (!Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'is_locked')) {
                $table->boolean('is_locked')->default(false)->after('is_dry_run');
            }
            if (!Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'rolled_back_at')) {
                $table->timestamp('rolled_back_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'retry_count')) {
                $table->unsignedTinyInteger('retry_count')->default(0)->after('failed_records');
            }
        });
        
        // Add performance indexes - check for existence first
        $connection->table(config('action-engine.tables.executions', 'bulk_action_executions'), function (Blueprint $table) {
            // Only add if index doesn't already exist (avoid duplicates)
            if (!$this->indexExists('bulk_action_executions', 'idx_executions_user_id')) {
                $table->index('user_id', 'idx_executions_user_id');
            }
            if (!$this->indexExists('bulk_action_executions', 'idx_executions_action_status')) {
                $table->index(['action_name', 'status'], 'idx_executions_action_status');
            }
            if (!$this->indexExists('bulk_action_executions', 'idx_executions_uuid_status')) {
                $table->index(['uuid', 'status'], 'idx_executions_uuid_status');
            }
            if (!$this->indexExists('bulk_action_executions', 'idx_executions_user_action_date')) {
                $table->index(['user_id', 'action_name', 'created_at'], 'idx_executions_user_action_date');
            }
        });

        // Add indexes to bulk_action_progress table
        $connection->table(config('action-engine.tables.progress', 'bulk_action_progress'), function (Blueprint $table) {
            // Note: foreign key column is bulk_action_execution_id, not execution_id
            if (!$this->indexExists('bulk_action_progress', 'idx_progress_execution_status_extra')) {
                $table->index(['bulk_action_execution_id', 'batch_number'], 'idx_progress_execution_status_extra');
            }
            if (!$this->indexExists('bulk_action_progress', 'idx_progress_started_at')) {
                $table->index('started_at', 'idx_progress_started_at');
            }
            if (!$this->indexExists('bulk_action_progress', 'idx_progress_completed_at')) {
                $table->index('completed_at', 'idx_progress_completed_at');
            }
        });

        // Add indexes to bulk_action_undo table
        $connection->table(config('action-engine.tables.undo', 'bulk_action_undo'), function (Blueprint $table) {
            // Add compression flag column
            if (!Schema::hasColumn(config('action-engine.tables.undo', 'bulk_action_undo'), 'is_compressed')) {
                $table->boolean('is_compressed')->default(false)->after('original_data');
            }
        });
        
        // Add additional indexes to bulk_action_undo table
        $connection->table(config('action-engine.tables.undo', 'bulk_action_undo'), function (Blueprint $table) {
            // Note: foreign key column is bulk_action_execution_id, not execution_id
            if (!$this->indexExists('bulk_action_undo', 'idx_undo_undone')) {
                $table->index(['undone', 'undone_at'], 'idx_undo_undone');
            }
            if (!$this->indexExists('bulk_action_undo', 'idx_undo_created_at')) {
                $table->index('created_at', 'idx_undo_created_at');
            }
        });

        // Add indexes to bulk_action_audit table
        $connection->table(config('action-engine.tables.audit', 'bulk_action_audit'), function (Blueprint $table) {
            // Add indexes that don't already exist
            if (!$this->indexExists('bulk_action_audit', 'idx_audit_user_date')) {
                $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
            }
            if (!$this->indexExists('bulk_action_audit', 'idx_audit_action_date')) {
                $table->index(['action_name', 'created_at'], 'idx_audit_action_date');
            }
            if (!$this->indexExists('bulk_action_audit', 'idx_audit_was_undone')) {
                $table->index(['was_undone', 'undone_at'], 'idx_audit_was_undone');
            }
        });
    }
    
    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::connection(config('action-engine.database_connection'));
        $schemaManager = $connection->getDoctrineSchemaManager();
        $tableName = config("action-engine.tables." . str_replace('bulk_action_', '', $table), $table);
        
        try {
            $indexes = $schemaManager->listTableIndexes($tableName);
            return isset($indexes[$indexName]) || isset($indexes[strtolower($indexName)]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::connection(config('action-engine.database_connection'));
        
        // Drop indexes from bulk_action_executions table
        $connection->table(config('action-engine.tables.executions', 'bulk_action_executions'), function (Blueprint $table) {
            if ($this->indexExists('bulk_action_executions', 'idx_executions_user_id')) {
                $table->dropIndex('idx_executions_user_id');
            }
            if ($this->indexExists('bulk_action_executions', 'idx_executions_action_status')) {
                $table->dropIndex('idx_executions_action_status');
            }
            if ($this->indexExists('bulk_action_executions', 'idx_executions_uuid_status')) {
                $table->dropIndex('idx_executions_uuid_status');
            }
            if ($this->indexExists('bulk_action_executions', 'idx_executions_user_action_date')) {
                $table->dropIndex('idx_executions_user_action_date');
            }
            
            // Drop added columns
            if (Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'is_locked')) {
                $table->dropColumn('is_locked');
            }
            if (Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'rolled_back_at')) {
                $table->dropColumn('rolled_back_at');
            }
            if (Schema::hasColumn(config('action-engine.tables.executions', 'bulk_action_executions'), 'retry_count')) {
                $table->dropColumn('retry_count');
            }
        });

        // Drop indexes from bulk_action_progress table
        $connection->table(config('action-engine.tables.progress', 'bulk_action_progress'), function (Blueprint $table) {
            if ($this->indexExists('bulk_action_progress', 'idx_progress_execution_status_extra')) {
                $table->dropIndex('idx_progress_execution_status_extra');
            }
            if ($this->indexExists('bulk_action_progress', 'idx_progress_started_at')) {
                $table->dropIndex('idx_progress_started_at');
            }
            if ($this->indexExists('bulk_action_progress', 'idx_progress_completed_at')) {
                $table->dropIndex('idx_progress_completed_at');
            }
        });

        // Drop indexes and columns from bulk_action_undo table
        $connection->table(config('action-engine.tables.undo', 'bulk_action_undo'), function (Blueprint $table) {
            if ($this->indexExists('bulk_action_undo', 'idx_undo_undone')) {
                $table->dropIndex('idx_undo_undone');
            }
            if ($this->indexExists('bulk_action_undo', 'idx_undo_created_at')) {
                $table->dropIndex('idx_undo_created_at');
            }
            
            if (Schema::hasColumn(config('action-engine.tables.undo', 'bulk_action_undo'), 'is_compressed')) {
                $table->dropColumn('is_compressed');
            }
        });

        // Drop indexes from bulk_action_audit table
        $connection->table(config('action-engine.tables.audit', 'bulk_action_audit'), function (Blueprint $table) {
            if ($this->indexExists('bulk_action_audit', 'idx_audit_user_date')) {
                $table->dropIndex('idx_audit_user_date');
            }
            if ($this->indexExists('bulk_action_audit', 'idx_audit_action_date')) {
                $table->dropIndex('idx_audit_action_date');
            }
            if ($this->indexExists('bulk_action_audit', 'idx_audit_was_undone')) {
                $table->dropIndex('idx_audit_was_undone');
            }
        });
    }
};
