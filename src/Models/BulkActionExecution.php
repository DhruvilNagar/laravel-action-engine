<?php

namespace DhruvilNagar\ActionEngine\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * BulkActionExecution Model
 * 
 * Represents a single execution of a bulk action, tracking its progress,
 * status, and metadata throughout its lifecycle.
 * 
 * @property string $uuid Unique identifier for the execution
 * @property string $action_name The registered action name being executed
 * @property string $model_type Fully qualified class name of target model
 * @property array $filters Query filters applied
 * @property array $parameters Action parameters
 * @property int $total_records Total number of records to process
 * @property int $processed_records Number of successfully processed records
 * @property int $failed_records Number of failed records
 * @property string $status Current status (pending, processing, completed, failed, cancelled)
 * @property int|null $user_id ID of user who initiated the action
 * @property string|null $user_type Morph type of user who initiated the action
 * @property \Carbon\Carbon|null $started_at When execution started
 * @property \Carbon\Carbon|null $completed_at When execution completed
 * @property \Carbon\Carbon|null $scheduled_for When action is scheduled to run
 * @property string|null $scheduled_timezone Timezone for scheduled execution
 * @property array|null $error_details Error information if failed
 * @property bool $can_undo Whether action can be undone
 * @property \Carbon\Carbon|null $undo_expires_at When undo capability expires
 * @property bool $is_dry_run Whether this is a dry run (preview mode)
 * @property array|null $dry_run_results Results from dry run
 * 
 * @property-read \Illuminate\Database\Eloquent\Collection|\DhruvilNagar\ActionEngine\Models\BulkActionProgress[] $progress
 * @property-read \Illuminate\Database\Eloquent\Collection|\DhruvilNagar\ActionEngine\Models\BulkActionUndo[] $undoRecords
 * @property-read \Illuminate\Database\Eloquent\Model|null $user
 */
class BulkActionExecution extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'action_name',
        'model_type',
        'filters',
        'parameters',
        'total_records',
        'processed_records',
        'failed_records',
        'status',
        'user_id',
        'user_type',
        'started_at',
        'completed_at',
        'scheduled_for',
        'scheduled_timezone',
        'error_details',
        'can_undo',
        'undo_expires_at',
        'is_dry_run',
        'dry_run_results',
        'batch_size',
        'queue_connection',
        'queue_name',
        'callbacks',
        'chain_config',
        'parent_execution_uuid',
    ];

    /**
     * The model's default attribute values.
     */
    protected $attributes = [
        'total_records' => 0,
        'processed_records' => 0,
        'failed_records' => 0,
        'status' => self::STATUS_PENDING,
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'filters' => 'array',
        'parameters' => 'array',
        'error_details' => 'array',
        'dry_run_results' => 'array',
        'callbacks' => 'array',
        'chain_config' => 'array',
        'can_undo' => 'boolean',
        'is_dry_run' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'undo_expires_at' => 'datetime',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
        'batch_size' => 'integer',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PARTIALLY_COMPLETED = 'partially_completed';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('action-engine.tables.executions', 'bulk_action_executions');
    }

    /**
     * Get the progress records for this execution.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(BulkActionProgress::class, 'bulk_action_execution_id');
    }

    /**
     * Get the undo records for this execution.
     */
    public function undoRecords(): HasMany
    {
        return $this->hasMany(BulkActionUndo::class, 'bulk_action_execution_id');
    }

    /**
     * Get the user who initiated this action.
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }

    /**
     * Get the parent execution (for chained actions).
     */
    public function parentExecution()
    {
        return $this->belongsTo(static::class, 'parent_execution_uuid', 'uuid');
    }

    /**
     * Get child executions (for chained actions).
     */
    public function childExecutions(): HasMany
    {
        return $this->hasMany(static::class, 'parent_execution_uuid', 'uuid');
    }

    /**
     * Scope for pending executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for scheduled executions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope for processing executions.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    /**
     * Scope for completed executions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed executions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for undoable executions.
     */
    public function scopeUndoable($query)
    {
        return $query->where('can_undo', true)
            ->where('undo_expires_at', '>', now());
    }

    /**
     * Scope for due scheduled actions.
     */
    public function scopeDue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('scheduled_for', '<=', now());
    }

    /**
     * Scope for actions by a specific user.
     */
    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user->getKey())
            ->where('user_type', get_class($user));
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_records === 0) {
            return 0;
        }

        return round(($this->processed_records / $this->total_records) * 100, 2);
    }

    /**
     * Get the success rate.
     */
    public function getSuccessRateAttribute(): float
    {
        if ($this->processed_records === 0) {
            return 0;
        }

        $successful = $this->processed_records - $this->failed_records;
        return round(($successful / $this->processed_records) * 100, 2);
    }

    /**
     * Check if the execution is in progress.
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Check if the execution is finished.
     */
    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_PARTIALLY_COMPLETED,
        ]);
    }

    /**
     * Check if the execution can be undone.
     */
    public function isUndoable(): bool
    {
        return $this->can_undo
            && $this->undo_expires_at
            && $this->undo_expires_at->isFuture()
            && $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Mark the execution as started.
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the execution as completed.
     */
    public function markAsCompleted(): void
    {
        $status = $this->failed_records > 0
            ? self::STATUS_PARTIALLY_COMPLETED
            : self::STATUS_COMPLETED;

        $this->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the execution as failed.
     */
    public function markAsFailed(array $errorDetails = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_details' => $errorDetails,
        ]);
    }

    /**     * Cancel the execution.
     */
    public function cancel(): void
    {
        if ($this->status !== self::STATUS_SCHEDULED && $this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Only scheduled or pending executions can be cancelled.');
        }

        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        event(new \DhruvilNagar\ActionEngine\Events\BulkActionCancelled($this));
    }

    /**     * Mark the execution as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Increment processed records count.
     */
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('processed_records', $count);
    }

    /**
     * Increment failed records count.
     */
    public function incrementFailed(int $count = 1): void
    {
        $this->increment('failed_records', $count);
    }

    /**
     * Get the route key name for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
