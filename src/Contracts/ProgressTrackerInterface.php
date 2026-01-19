<?php

namespace DhruvilNagar\ActionEngine\Contracts;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionProgress;

/**
 * Interface for progress tracking implementations.
 */
interface ProgressTrackerInterface
{
    /**
     * Initialize progress tracking for an execution.
     *
     * @param BulkActionExecution $execution
     * @param int $totalBatches
     * @param int $batchSize
     */
    public function initialize(BulkActionExecution $execution, int $totalBatches, int $batchSize): void;

    /**
     * Update progress for the execution.
     *
     * @param BulkActionExecution $execution
     * @param int $processedCount Number of records processed in this update
     * @param array $affectedIds IDs of affected records
     */
    public function update(BulkActionExecution $execution, int $processedCount, array $affectedIds = []): void;

    /**
     * Get the current progress percentage.
     *
     * @param BulkActionExecution $execution
     * @return float Progress percentage (0-100)
     */
    public function getProgress(BulkActionExecution $execution): float;

    /**
     * Mark a batch as started.
     *
     * @param BulkActionProgress $progress
     */
    public function startBatch(BulkActionProgress $progress): void;

    /**
     * Mark a batch as completed.
     *
     * @param BulkActionProgress $progress
     * @param array $affectedIds
     */
    public function completeBatch(BulkActionProgress $progress, array $affectedIds = []): void;

    /**
     * Mark a batch as failed.
     *
     * @param BulkActionProgress $progress
     * @param string $error Error message
     */
    public function failBatch(BulkActionProgress $progress, string $error): void;

    /**
     * Get detailed progress information.
     *
     * @param BulkActionExecution $execution
     * @return array{
     *     total_records: int,
     *     processed_records: int,
     *     failed_records: int,
     *     progress_percentage: float,
     *     estimated_time_remaining: string|null,
     *     batches: array{total: int, completed: int, processing: int, failed: int, pending: int}
     * }
     */
    public function getDetails(BulkActionExecution $execution): array;

    /**
     * Broadcast progress update.
     *
     * @param BulkActionExecution $execution
     */
    public function broadcast(BulkActionExecution $execution): void;
}
