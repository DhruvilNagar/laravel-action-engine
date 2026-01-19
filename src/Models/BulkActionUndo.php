<?php

namespace DhruvilNagar\ActionEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkActionUndo extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'bulk_action_execution_id',
        'model_type',
        'model_id',
        'original_data',
        'changes',
        'undo_action_type',
        'undone',
        'undone_at',
        'undone_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'original_data' => 'array',
        'changes' => 'array',
        'undone' => 'boolean',
        'undone_at' => 'datetime',
        'model_id' => 'integer',
        'undone_by' => 'integer',
    ];

    /**
     * Undo action type constants.
     */
    public const ACTION_RESTORE = 'restore';
    public const ACTION_DELETE = 'delete';
    public const ACTION_UPDATE = 'update';
    public const ACTION_RECREATE = 'recreate';

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('action-engine.tables.undo', 'bulk_action_undo');
    }

    /**
     * Get the execution this undo record belongs to.
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(BulkActionExecution::class, 'bulk_action_execution_id');
    }

    /**
     * Get the original model instance.
     */
    public function getOriginalModel(): ?Model
    {
        $modelClass = $this->model_type;

        if (!class_exists($modelClass)) {
            return null;
        }

        return $modelClass::withTrashed()->find($this->model_id);
    }

    /**
     * Scope for records that haven't been undone.
     */
    public function scopeNotUndone($query)
    {
        return $query->where('undone', false);
    }

    /**
     * Scope for records that have been undone.
     */
    public function scopeUndone($query)
    {
        return $query->where('undone', true);
    }

    /**
     * Scope by model type.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Check if this record has been undone.
     */
    public function isUndone(): bool
    {
        return $this->undone;
    }

    /**
     * Mark this record as undone.
     */
    public function markAsUndone(?int $userId = null): void
    {
        $this->update([
            'undone' => true,
            'undone_at' => now(),
            'undone_by' => $userId,
        ]);
    }

    /**
     * Get a specific field from the original data.
     */
    public function getOriginalField(string $field, mixed $default = null): mixed
    {
        return $this->original_data[$field] ?? $default;
    }

    /**
     * Get the changes that were applied.
     */
    public function getChanges(): array
    {
        return $this->changes ?? [];
    }
}
