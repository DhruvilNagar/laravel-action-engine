<?php

namespace DhruvilNagar\ActionEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BulkActionProgress Model
 * 
 * Tracks the progress of individual batches within a bulk action execution.
 * 
 * Each execution is divided into batches for efficient processing. This model
 * maintains detailed information about each batch including processing status,
 * affected records, failures, and timing information.
 * 
 * @property int $id
 * @property int $bulk_action_execution_id Parent execution ID
 * @property int $batch_number Sequential batch number (1-indexed)
 * @property int $batch_size Configured size of this batch
 * @property int $processed_count Number of records processed in this batch
 * @property int $failed_count Number of records that failed in this batch
 * @property string $status Current batch status (pending, processing, completed, failed)
 * @property array|null $affected_ids Array of successfully processed record IDs
 * @property array|null $failed_ids Array of failed record IDs
 * @property string|null $error_message Error message if batch failed
 * @property array|null $error_details Detailed error information
 * @property \Carbon\Carbon|null $started_at When batch processing started
 * @property \Carbon\Carbon|null $completed_at When batch processing completed
 * @property int $retry_count Number of retry attempts
 * 
 * @property-read BulkActionExecution $execution
 */
class BulkActionProgress extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bulk_action_execution_id',
        'batch_number',
        'batch_size',
        'processed_count',
        'failed_count',
        'status',
        'affected_ids',
        'failed_ids',
        'error_message',
        'error_details',
        'started_at',
        'completed_at',
        'retry_count',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'affected_ids' => 'array',
        'failed_ids' => 'array',
        'error_details' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'batch_number' => 'integer',
        'batch_size' => 'integer',
        'processed_count' => 'integer',
        'failed_count' => 'integer',
        'retry_count' => 'integer',
    ];

    /**
     * Batch status constants.
     * 
     * Lifecycle: pending -> processing -> (completed | failed)
     */
    public const STATUS_PENDING = 'pending';      // Batch queued, not yet started
    public const STATUS_PROCESSING = 'processing'; // Batch currently being processed
    public const STATUS_COMPLETED = 'completed';   // Batch successfully completed
    public const STATUS_FAILED = 'failed';         // Batch failed with errors

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('action-engine.tables.progress', 'bulk_action_progress');
    }

    /**
     * Get the execution this progress belongs to.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(BulkActionExecution::class, 'bulk_action_execution_id');
    }

    /**
     * Scope for pending batches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for processing batches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for completed batches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed batches.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Get the progress percentage for this batch.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->batch_size === 0) {
            return 0;
        }

        return round(($this->processed_count / $this->batch_size) * 100, 2);
    }

    /**
     * Check if the batch is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the batch has failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark the batch as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the batch as completed.
     */
    public function markAsCompleted(array $affectedIds = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'affected_ids' => $affectedIds,
            'processed_count' => count($affectedIds),
        ]);
    }

    /**
     * Mark the batch as failed.
     */
    public function markAsFailed(string $errorMessage, array $errorDetails = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }
}
