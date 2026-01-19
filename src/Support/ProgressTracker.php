<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Contracts\ProgressTrackerInterface;
use DhruvilNagar\ActionEngine\Events\BulkActionProgress as ProgressEvent;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionProgress;
use Illuminate\Support\Facades\Cache;

/**
 * ProgressTracker
 * 
 * Tracks and broadcasts progress updates for bulk action executions.
 * 
 * Uses cache-based checkpointing for efficient progress calculation and
 * estimated time remaining. Throttles broadcast events to prevent overwhelming
 * listeners with frequent updates.
 * 
 * Features:
 * - Real-time progress percentage calculation
 * - ETA estimation based on processing rate
 * - Throttled event broadcasting (configurable)
 * - Batch-level progress tracking
 * - Memory-efficient checkpoint management
 */
class ProgressTracker implements ProgressTrackerInterface
{
    /**
     * Timestamp map of last broadcast event per execution UUID.
     * Used for throttling to prevent excessive event dispatching.
     */
    protected array $lastBroadcast = [];

    /**
     * Initialize progress tracking infrastructure for an execution.
     * 
     * Sets up cache-based tracking with checkpoint system for efficient
     * progress monitoring and ETA calculation.
     *
     * @param BulkActionExecution $execution The execution to track
     * @param int $totalBatches Total number of batches to process
     * @param int $batchSize Number of records per batch
     * @return void
     */
    public function initialize(BulkActionExecution $execution, int $totalBatches, int $batchSize): void
    {
        // Cache initialization data for quick access during processing
        $cacheKey = "bulk_action_progress_{$execution->uuid}";
        
        Cache::put($cacheKey, [
            'total_batches' => $totalBatches,
            'batch_size' => $batchSize,
            'started_at' => now()->timestamp,
            'processed_at_checkpoints' => [],
        ], now()->addDay());
    }

    /**
     * Update progress for the execution.
     */
    public function update(BulkActionExecution $execution, int $processedCount, array $affectedIds = []): void
    {
        $execution->increment('processed_records', $processedCount);

        // Store checkpoint for time estimation
        $cacheKey = "bulk_action_progress_{$execution->uuid}";
        $progressData = Cache::get($cacheKey, []);
        
        $progressData['processed_at_checkpoints'][] = [
            'count' => $execution->processed_records,
            'timestamp' => now()->timestamp,
        ];

        // Keep only last 10 checkpoints
        $progressData['processed_at_checkpoints'] = array_slice(
            $progressData['processed_at_checkpoints'],
            -10
        );

        Cache::put($cacheKey, $progressData, now()->addDay());

        // Broadcast if enabled and throttle allows
        $this->throttledBroadcast($execution);
    }

    /**
     * Get the current progress percentage.
     */
    public function getProgress(BulkActionExecution $execution): float
    {
        if ($execution->total_records === 0) {
            return 0;
        }

        return round(($execution->processed_records / $execution->total_records) * 100, 2);
    }

    /**
     * Mark a batch as started.
     */
    public function startBatch(BulkActionProgress $progress): void
    {
        $progress->markAsStarted();
    }

    /**
     * Mark a batch as completed.
     */
    public function completeBatch(BulkActionProgress $progress, array $affectedIds = []): void
    {
        $progress->markAsCompleted($affectedIds);
    }

    /**
     * Mark a batch as failed.
     */
    public function failBatch(BulkActionProgress $progress, string $error): void
    {
        $progress->markAsFailed($error);
    }

    /**
     * Get detailed progress information.
     */
    public function getDetails(BulkActionExecution $execution): array
    {
        $batches = $execution->progress()->get();
        
        $batchStats = [
            'total' => $batches->count(),
            'completed' => $batches->where('status', BulkActionProgress::STATUS_COMPLETED)->count(),
            'processing' => $batches->where('status', BulkActionProgress::STATUS_PROCESSING)->count(),
            'failed' => $batches->where('status', BulkActionProgress::STATUS_FAILED)->count(),
            'pending' => $batches->where('status', BulkActionProgress::STATUS_PENDING)->count(),
        ];

        return [
            'uuid' => $execution->uuid,
            'status' => $execution->status,
            'total_records' => $execution->total_records,
            'processed_records' => $execution->processed_records,
            'failed_records' => $execution->failed_records,
            'progress_percentage' => $this->getProgress($execution),
            'estimated_time_remaining' => $this->estimateTimeRemaining($execution),
            'batches' => $batchStats,
            'started_at' => $execution->started_at?->toIso8601String(),
            'elapsed_time' => $execution->started_at 
                ? $execution->started_at->diffForHumans(now(), true) 
                : null,
        ];
    }

    /**
     * Broadcast progress update.
     */
    public function broadcast(BulkActionExecution $execution): void
    {
        if (!config('action-engine.broadcasting.enabled', false)) {
            return;
        }

        event(new ProgressEvent($execution, $this->getDetails($execution)));
    }

    /**
     * Throttled broadcast to prevent too many updates.
     */
    protected function throttledBroadcast(BulkActionExecution $execution): void
    {
        if (!config('action-engine.broadcasting.enabled', false)) {
            return;
        }

        $throttleMs = config('action-engine.progress.broadcast_throttle_ms', 500);
        $lastBroadcast = $this->lastBroadcast[$execution->uuid] ?? 0;
        $now = microtime(true) * 1000;

        if (($now - $lastBroadcast) >= $throttleMs) {
            $this->broadcast($execution);
            $this->lastBroadcast[$execution->uuid] = $now;
        }
    }

    /**
     * Estimate remaining time.
     */
    protected function estimateTimeRemaining(BulkActionExecution $execution): ?string
    {
        if ($execution->processed_records === 0 || $execution->isFinished()) {
            return null;
        }

        $cacheKey = "bulk_action_progress_{$execution->uuid}";
        $progressData = Cache::get($cacheKey, []);
        
        $checkpoints = $progressData['processed_at_checkpoints'] ?? [];
        
        if (count($checkpoints) < 2) {
            return null;
        }

        // Calculate average processing rate from checkpoints
        $firstCheckpoint = reset($checkpoints);
        $lastCheckpoint = end($checkpoints);

        $recordsProcessed = $lastCheckpoint['count'] - $firstCheckpoint['count'];
        $timeElapsed = $lastCheckpoint['timestamp'] - $firstCheckpoint['timestamp'];

        if ($timeElapsed === 0 || $recordsProcessed === 0) {
            return null;
        }

        $rate = $recordsProcessed / $timeElapsed; // records per second
        $remaining = $execution->total_records - $execution->processed_records;
        $secondsRemaining = $remaining / $rate;

        return $this->formatDuration($secondsRemaining);
    }

    /**
     * Format duration in human-readable format.
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return ceil($seconds) . ' seconds';
        }

        if ($seconds < 3600) {
            $minutes = ceil($seconds / 60);
            return $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }

        $hours = floor($seconds / 3600);
        $minutes = ceil(($seconds % 3600) / 60);
        
        $result = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        if ($minutes > 0) {
            $result .= ' ' . $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }

        return $result;
    }

    /**
     * Clear progress cache for an execution.
     */
    public function clearCache(BulkActionExecution $execution): void
    {
        $cacheKey = "bulk_action_progress_{$execution->uuid}";
        Cache::forget($cacheKey);
        unset($this->lastBroadcast[$execution->uuid]);
    }
}
