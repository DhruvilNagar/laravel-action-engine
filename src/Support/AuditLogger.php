<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Models\BulkActionAudit;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Throwable;

/**
 * AuditLogger
 * 
 * Provides comprehensive audit trail logging for bulk action executions.
 * 
 * Creates and maintains audit records throughout the action lifecycle,
 * capturing execution details, affected records, user information, and errors.
 * 
 * Audit logging can be globally enabled/disabled via config and includes
 * intelligent data limiting to prevent excessive storage usage.
 * 
 * Features:
 * - Lifecycle event logging (started, completed, failed, undone)
 * - Affected record tracking (with configurable limits)
 * - Error details capture
 * - User attribution
 * - Configurable data retention
 */
class AuditLogger
{
    /**
     * Log the start of a bulk action execution.
     * 
     * Creates an initial audit record when an action begins processing.
     *
     * @param BulkActionExecution $execution The execution being started
     * @return void
     */
    public function logStarted(BulkActionExecution $execution): void
    {
        if (!config('action-engine.audit.enabled', true)) {
            return;
        }

        BulkActionAudit::createFromExecution($execution, [
            'notes' => 'Action started',
        ]);
    }

    /**
     * Log successful completion of a bulk action.
     * 
     * Updates the audit record with final statistics including processed
     * and failed record counts. Optionally includes affected record IDs
     * (limited to prevent excessive data storage).
     *
     * @param BulkActionExecution $execution The completed execution
     * @return void
     */
    public function logCompleted(BulkActionExecution $execution): void
    {
        if (!config('action-engine.audit.enabled', true)) {
            return;
        }

        $audit = BulkActionAudit::where('execution_uuid', $execution->uuid)->first();

        if ($audit) {
            $updateData = [
                'status' => $execution->status,
                'processed_records' => $execution->processed_records,
                'failed_records' => $execution->failed_records,
                'completed_at' => $execution->completed_at,
            ];

            // Include affected IDs if configured
            if (config('action-engine.audit.log_affected_ids', true)) {
                $affectedIds = $execution->progress()
                    ->pluck('affected_ids')
                    ->flatten()
                    ->filter()
                    ->toArray();

                // Limit to prevent huge data
                $updateData['affected_ids'] = array_slice($affectedIds, 0, 10000);
            }

            $audit->update($updateData);
        } else {
            BulkActionAudit::createFromExecution($execution, [
                'notes' => 'Action completed',
            ]);
        }
    }

    /**
     * Log a bulk action execution failure.
     * 
     * Captures exception details and partial progress information
     * when an action fails during execution.
     *
     * @param BulkActionExecution $execution The failed execution
     * @param Throwable $exception The exception that caused the failure
     * @return void
     */
    public function logFailed(BulkActionExecution $execution, Throwable $exception): void
    {
        if (!config('action-engine.audit.enabled', true)) {
            return;
        }

        $audit = BulkActionAudit::where('execution_uuid', $execution->uuid)->first();
        
        $notes = "Action failed: {$exception->getMessage()}";

        if ($audit) {
            $audit->update([
                'status' => $execution->status,
                'processed_records' => $execution->processed_records,
                'failed_records' => $execution->failed_records,
                'completed_at' => $execution->completed_at,
                'notes' => $notes,
            ]);
        } else {
            BulkActionAudit::createFromExecution($execution, [
                'notes' => $notes,
            ]);
        }
    }

    /**
     * Log when an action is undone.
     */
    public function logUndone(BulkActionExecution $execution): void
    {
        if (!config('action-engine.audit.enabled', true)) {
            return;
        }

        $audit = BulkActionAudit::where('execution_uuid', $execution->uuid)->first();

        if ($audit) {
            $audit->markAsUndone(auth()->id());
        }
    }

    /**
     * Log when an action is cancelled.
     */
    public function logCancelled(BulkActionExecution $execution): void
    {
        if (!config('action-engine.audit.enabled', true)) {
            return;
        }

        $audit = BulkActionAudit::where('execution_uuid', $execution->uuid)->first();

        if ($audit) {
            $audit->update([
                'status' => $execution->status,
                'completed_at' => now(),
                'notes' => 'Action cancelled by user',
            ]);
        }
    }

    /**
     * Clean up old audit logs.
     */
    public function cleanup(): int
    {
        $retentionDays = config('action-engine.audit.retention_days', 90);
        $cutoffDate = now()->subDays($retentionDays);

        return BulkActionAudit::where('created_at', '<', $cutoffDate)->delete();
    }
}
