<?php

namespace DhruvilNagar\ActionEngine\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MemoryOptimizer
 * 
 * Handles memory management and optimization for bulk operations.
 * Monitors memory usage and automatically adjusts batch sizes to prevent exhaustion.
 */
class MemoryOptimizer
{
    /**
     * Default memory threshold percentage (80%)
     */
    protected float $memoryThreshold = 0.8;

    /**
     * Minimum batch size
     */
    protected int $minBatchSize = 10;

    /**
     * Maximum batch size
     */
    protected int $maxBatchSize = 10000;

    /**
     * Memory samples for averaging
     */
    protected array $memorySamples = [];

    /**
     * Maximum number of samples to keep
     */
    protected int $maxSamples = 10;

    /**
     * Create a new memory optimizer instance.
     */
    public function __construct(
        ?float $memoryThreshold = null,
        ?int $minBatchSize = null,
        ?int $maxBatchSize = null
    ) {
        $this->memoryThreshold = $memoryThreshold ?? config('action-engine.performance.memory_threshold', 0.8);
        $this->minBatchSize = $minBatchSize ?? config('action-engine.performance.min_batch_size', 10);
        $this->maxBatchSize = $maxBatchSize ?? config('action-engine.performance.max_batch_size', 10000);
    }

    /**
     * Get current memory usage percentage.
     */
    public function getCurrentMemoryUsage(): float
    {
        $memoryLimit = $this->getMemoryLimit();
        $currentUsage = memory_get_usage(true);

        if ($memoryLimit === -1) {
            return 0; // No limit
        }

        return $currentUsage / $memoryLimit;
    }

    /**
     * Get PHP memory limit in bytes.
     */
    public function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return -1; // Unlimited
        }

        return $this->convertToBytes($memoryLimit);
    }

    /**
     * Get current memory usage in human-readable format.
     */
    public function getCurrentMemoryFormatted(): string
    {
        return $this->formatBytes(memory_get_usage(true));
    }

    /**
     * Get peak memory usage in human-readable format.
     */
    public function getPeakMemoryFormatted(): string
    {
        return $this->formatBytes(memory_get_peak_usage(true));
    }

    /**
     * Check if memory usage is approaching the limit.
     */
    public function isApproachingLimit(): bool
    {
        return $this->getCurrentMemoryUsage() >= $this->memoryThreshold;
    }

    /**
     * Get recommended batch size based on current memory usage.
     */
    public function getRecommendedBatchSize(int $currentBatchSize, int $recordSize = 0): int
    {
        $memoryUsage = $this->getCurrentMemoryUsage();

        // If we're below threshold, we can increase batch size
        if ($memoryUsage < 0.5) {
            $newSize = (int) ($currentBatchSize * 1.5);
            return min($newSize, $this->maxBatchSize);
        }

        // If we're approaching threshold, reduce batch size
        if ($memoryUsage >= $this->memoryThreshold) {
            $newSize = (int) ($currentBatchSize * 0.5);
            return max($newSize, $this->minBatchSize);
        }

        // Otherwise, keep current size
        return $currentBatchSize;
    }

    /**
     * Calculate optimal batch size based on available memory and record size.
     */
    public function calculateOptimalBatchSize(int $estimatedRecordSize): int
    {
        $memoryLimit = $this->getMemoryLimit();
        
        if ($memoryLimit === -1) {
            return $this->maxBatchSize; // No limit, use max
        }

        $currentUsage = memory_get_usage(true);
        $availableMemory = $memoryLimit - $currentUsage;
        $safeMemory = (int) ($availableMemory * $this->memoryThreshold);

        if ($estimatedRecordSize <= 0) {
            $estimatedRecordSize = 1024; // Default 1KB per record
        }

        $optimalSize = (int) ($safeMemory / $estimatedRecordSize);

        return max(
            $this->minBatchSize,
            min($optimalSize, $this->maxBatchSize)
        );
    }

    /**
     * Force garbage collection to free up memory.
     */
    public function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Clear query log to free memory.
     */
    public function clearQueryLog(): void
    {
        DB::flushQueryLog();
    }

    /**
     * Optimize memory before processing a batch.
     */
    public function optimizeBeforeBatch(): void
    {
        // Clear query log if enabled
        if (DB::logging()) {
            $this->clearQueryLog();
        }

        // Force garbage collection if approaching limit
        if ($this->isApproachingLimit()) {
            $this->forceGarbageCollection();
        }
    }

    /**
     * Record memory usage sample.
     */
    public function recordMemorySample(): void
    {
        $this->memorySamples[] = memory_get_usage(true);

        // Keep only recent samples
        if (count($this->memorySamples) > $this->maxSamples) {
            array_shift($this->memorySamples);
        }
    }

    /**
     * Get average memory usage from samples.
     */
    public function getAverageMemoryUsage(): float
    {
        if (empty($this->memorySamples)) {
            return memory_get_usage(true);
        }

        return array_sum($this->memorySamples) / count($this->memorySamples);
    }

    /**
     * Estimate memory per record based on samples.
     */
    public function estimateMemoryPerRecord(int $recordsProcessed): int
    {
        if ($recordsProcessed <= 0 || empty($this->memorySamples)) {
            return 1024; // Default 1KB
        }

        $memoryUsed = end($this->memorySamples) - reset($this->memorySamples);
        return max(1024, (int) ($memoryUsed / $recordsProcessed));
    }

    /**
     * Log memory usage information.
     */
    public function logMemoryUsage(string $context = 'bulk_action'): void
    {
        Log::channel('bulk_actions')->info("Memory usage [{$context}]", [
            'current' => $this->getCurrentMemoryFormatted(),
            'peak' => $this->getPeakMemoryFormatted(),
            'limit' => $this->formatBytes($this->getMemoryLimit()),
            'usage_percentage' => round($this->getCurrentMemoryUsage() * 100, 2) . '%',
        ]);
    }

    /**
     * Check if we should pause processing due to memory.
     */
    public function shouldPauseProcessing(): bool
    {
        return $this->getCurrentMemoryUsage() >= 0.95; // 95% threshold for emergency pause
    }

    /**
     * Convert memory string to bytes.
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        return match ($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Reset memory tracking.
     */
    public function reset(): void
    {
        $this->memorySamples = [];
    }

    /**
     * Get memory statistics.
     */
    public function getStatistics(): array
    {
        return [
            'current_usage' => $this->getCurrentMemoryFormatted(),
            'peak_usage' => $this->getPeakMemoryFormatted(),
            'memory_limit' => $this->formatBytes($this->getMemoryLimit()),
            'usage_percentage' => round($this->getCurrentMemoryUsage() * 100, 2),
            'is_approaching_limit' => $this->isApproachingLimit(),
            'average_usage' => $this->formatBytes((int) $this->getAverageMemoryUsage()),
            'samples_count' => count($this->memorySamples),
        ];
    }
}
