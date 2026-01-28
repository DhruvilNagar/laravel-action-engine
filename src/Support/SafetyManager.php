<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Support\Facades\DB;

class SafetyManager
{
    /**
     * Require confirmation for destructive operations
     */
    public function requiresConfirmation(string $actionType): bool
    {
        $destructiveActions = config('action-engine.destructive_actions', [
            'delete',
            'force_delete',
            'truncate'
        ]);

        return in_array($actionType, $destructiveActions);
    }

    /**
     * Soft delete records before hard delete
     */
    public function softDeleteBeforeHardDelete(string $modelClass, array $ids): bool
    {
        if (!config('action-engine.soft_delete_before_hard_delete', true)) {
            return false;
        }

        // Check if model uses SoftDeletes trait
        $model = new $modelClass;
        
        if (!method_exists($model, 'forceDelete')) {
            return false;
        }

        DB::transaction(function () use ($modelClass, $ids) {
            $modelClass::whereIn('id', $ids)->delete();
        });

        return true;
    }

    /**
     * Lock records during processing
     */
    public function lockRecords(BulkActionExecution $execution): void
    {
        if (!config('action-engine.enable_record_locking', true)) {
            return;
        }

        $execution->update(['is_locked' => true]);
    }

    /**
     * Unlock records after processing
     */
    public function unlockRecords(BulkActionExecution $execution): void
    {
        $execution->update(['is_locked' => false]);
    }

    /**
     * Check if dry run is required for first-time destructive operations
     */
    public function requiresDryRun(string $actionType, int $userId): bool
    {
        if (!config('action-engine.require_dry_run_first_time', true)) {
            return false;
        }

        if (!$this->requiresConfirmation($actionType)) {
            return false;
        }

        // Check if user has run this action type before
        $hasRunBefore = BulkActionExecution::where('action_type', $actionType)
            ->where('created_by', $userId)
            ->where('is_dry_run', false)
            ->exists();

        return !$hasRunBefore;
    }

    /**
     * Implement rollback strategy for partial failures
     */
    public function rollbackPartialFailure(BulkActionExecution $execution): void
    {
        if (!config('action-engine.rollback_on_partial_failure', true)) {
            return;
        }

        DB::transaction(function () use ($execution) {
            // Get all successfully processed records
            $processedRecords = DB::table('bulk_action_progress')
                ->where('execution_id', $execution->id)
                ->where('status', 'completed')
                ->get();

            // Rollback each record
            foreach ($processedRecords as $record) {
                $this->rollbackSingleRecord($record);
            }

            // Mark execution as rolled back
            $execution->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now()
            ]);
        });
    }

    /**
     * Rollback a single record
     */
    protected function rollbackSingleRecord($record): void
    {
        // Implementation depends on action type
        // This is a placeholder for the actual rollback logic
    }

    /**
     * Add confirmation prompt data
     */
    public function getConfirmationPrompt(string $actionType, int $recordCount): array
    {
        return [
            'title' => 'Confirm Destructive Action',
            'message' => "You are about to {$actionType} {$recordCount} records. This action may be irreversible.",
            'confirmText' => 'I understand the consequences',
            'requiresTyping' => $recordCount > 1000,
            'typeText' => strtoupper($actionType),
            'showWarning' => true,
            'warningLevel' => $recordCount > 10000 ? 'critical' : 'high'
        ];
    }

    /**
     * Validate confirmation input
     */
    public function validateConfirmation(string $actionType, string $input): bool
    {
        return strtoupper($input) === strtoupper($actionType);
    }
}
