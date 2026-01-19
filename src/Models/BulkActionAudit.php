<?php

namespace DhruvilNagar\ActionEngine\Models;

use Illuminate\Database\Eloquent\Model;

class BulkActionAudit extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'execution_uuid',
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
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
        'affected_ids',
        'was_undone',
        'undone_at',
        'undone_by',
        'started_at',
        'completed_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'filters' => 'array',
        'parameters' => 'array',
        'affected_ids' => 'array',
        'was_undone' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'undone_at' => 'datetime',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'failed_records' => 'integer',
        'user_id' => 'integer',
        'undone_by' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('action-engine.tables.audit', 'bulk_action_audit');
    }

    /**
     * Scope by action name.
     */
    public function scopeAction($query, string $actionName)
    {
        return $query->where('action_name', $actionName);
    }

    /**
     * Scope by model type.
     */
    public function scopeForModel($query, string $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope by user.
     */
    public function scopeByUser($query, $user)
    {
        return $query->where('user_id', $user->getKey())
            ->where('user_type', get_class($user));
    }

    /**
     * Scope by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for undone actions.
     */
    public function scopeUndone($query)
    {
        return $query->where('was_undone', true);
    }

    /**
     * Scope for actions within a date range.
     */
    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Search audit logs.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('action_name', 'like', "%{$term}%")
                ->orWhere('model_type', 'like', "%{$term}%")
                ->orWhere('user_name', 'like', "%{$term}%")
                ->orWhere('user_email', 'like', "%{$term}%")
                ->orWhere('execution_uuid', 'like', "%{$term}%");
        });
    }

    /**
     * Create an audit entry from an execution.
     */
    public static function createFromExecution(BulkActionExecution $execution, array $additionalData = []): self
    {
        $user = $execution->user;

        return static::create(array_merge([
            'execution_uuid' => $execution->uuid,
            'action_name' => $execution->action_name,
            'model_type' => $execution->model_type,
            'filters' => $execution->filters,
            'parameters' => $execution->parameters,
            'total_records' => $execution->total_records,
            'processed_records' => $execution->processed_records,
            'failed_records' => $execution->failed_records,
            'status' => $execution->status,
            'user_id' => $execution->user_id,
            'user_type' => $execution->user_type,
            'user_name' => $user?->name ?? null,
            'user_email' => $user?->email ?? null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
        ], $additionalData));
    }

    /**
     * Mark this audit entry as undone.
     */
    public function markAsUndone(?int $userId = null): void
    {
        $this->update([
            'was_undone' => true,
            'undone_at' => now(),
            'undone_by' => $userId,
        ]);
    }

    /**
     * Get the duration of the action.
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffForHumans($this->completed_at, true);
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
}
