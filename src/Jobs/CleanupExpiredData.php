<?php

namespace DhruvilNagar\ActionEngine\Jobs;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionProgress;
use DhruvilNagar\ActionEngine\Support\AuditLogger;
use DhruvilNagar\ActionEngine\Support\UndoManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupExpiredData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(UndoManager $undoManager, AuditLogger $auditLogger): void
    {
        // Cleanup expired undo data
        $undoManager->cleanup();

        // Cleanup old progress records
        $this->cleanupProgress();

        // Cleanup old executions
        $this->cleanupExecutions();

        // Cleanup old audit logs
        $auditLogger->cleanup();
    }

    /**
     * Cleanup old progress records.
     */
    protected function cleanupProgress(): void
    {
        $retentionDays = config('action-engine.cleanup.progress_retention_days', 7);
        $cutoffDate = now()->subDays($retentionDays);

        BulkActionProgress::whereHas('execution', function ($query) use ($cutoffDate) {
            $query->whereIn('status', [
                BulkActionExecution::STATUS_COMPLETED,
                BulkActionExecution::STATUS_FAILED,
                BulkActionExecution::STATUS_CANCELLED,
                BulkActionExecution::STATUS_PARTIALLY_COMPLETED,
            ])->where('completed_at', '<', $cutoffDate);
        })->delete();
    }

    /**
     * Cleanup old execution records.
     */
    protected function cleanupExecutions(): void
    {
        $retentionDays = config('action-engine.cleanup.execution_retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        // Only delete completed/failed/cancelled executions older than retention period
        BulkActionExecution::whereIn('status', [
            BulkActionExecution::STATUS_COMPLETED,
            BulkActionExecution::STATUS_FAILED,
            BulkActionExecution::STATUS_CANCELLED,
            BulkActionExecution::STATUS_PARTIALLY_COMPLETED,
        ])
        ->where('completed_at', '<', $cutoffDate)
        ->where('can_undo', false) // Don't delete if undo is still possible
        ->delete();
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['bulk-action', 'cleanup'];
    }
}
