<?php

namespace DhruvilNagar\ActionEngine\Actions;

use Carbon\Carbon;
use Closure;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Fluent builder for constructing and executing bulk actions.
 * 
 * This builder provides a chainable API for defining bulk operations on Eloquent models,
 * with support for filtering, batching, scheduling, progress tracking, and undo functionality.
 * 
 * @example
 * BulkAction::on(User::class)
 *     ->action('delete')
 *     ->where('status', 'inactive')
 *     ->withUndo(7)
 *     ->sync()
 *     ->execute();
 */
class BulkActionBuilder
{
    /**
     * The action executor instance.
     */
    protected ActionExecutor $executor;

    /**
     * The fully qualified class name of the Eloquent model to perform actions on.
     */
    protected ?string $modelClass = null;

    /**
     * The registered action name to execute (e.g., 'delete', 'update', 'archive').
     */
    protected ?string $actionName = null;

    /**
     * Additional query filters for advanced filtering.
     * 
     * @deprecated Use whereConditions instead
     */
    protected array $filters = [];

    /**
     * WHERE conditions to filter target records.
     */
    protected array $whereConditions = [];

    /**
     * Specific primary key IDs to target. When set, other filters are ignored.
     */
    protected array $targetIds = [];

    /**
     * Parameters to pass to the action handler.
     */
    protected array $parameters = [];

    /**
     * Number of records to process per batch.
     */
    protected ?int $batchSize = null;

    /**
     * Whether to run synchronously.
     */
    protected bool $shouldQueue = true;

    /**
     * Queue connection.
     */
    protected ?string $queueConnection = null;

    /**
     * Queue name.
     */
    protected ?string $queueName = null;

    /**
     * Scheduled datetime.
     */
    protected ?Carbon $scheduledFor = null;

    /**
     * Scheduled timezone.
     */
    protected ?string $scheduledTimezone = null;

    /**
     * Whether to run in dry run mode.
     */
    protected bool $isDryRun = false;

    /**
     * Whether to enable undo.
     */
    protected bool $withUndo = false;

    /**
     * Undo expiry days.
     */
    protected int $undoExpiryDays = 7;

    /**
     * Custom authorization callback.
     */
    protected ?Closure $authorizationCallback = null;

    /**
     * Progress callback.
     */
    protected ?Closure $progressCallback = null;

    /**
     * Completion callback.
     */
    protected ?Closure $completeCallback = null;

    /**
     * Failure callback.
     */
    protected ?Closure $failureCallback = null;

    /**
     * The authenticated user.
     */
    protected mixed $user = null;

    /**
     * Action chain configuration.
     */
    protected array $chainConfig = [];

    /**
     * Create a new builder instance.
     */
    public function __construct(ActionExecutor $executor)
    {
        $this->executor = $executor;
        $this->user = Auth::user();
    }

    /**
     * Set the model class to perform action on.
     */
    public function on(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Define the action to perform.
     */
    public function action(string $actionName): self
    {
        $this->actionName = $actionName;
        return $this;
    }

    /**
     * Add WHERE condition.
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof Closure) {
            $this->whereConditions[] = ['type' => 'closure', 'callback' => $column];
        } elseif (func_num_args() === 2) {
            $this->whereConditions[] = ['type' => 'basic', 'column' => $column, 'operator' => '=', 'value' => $operator];
        } else {
            $this->whereConditions[] = ['type' => 'basic', 'column' => $column, 'operator' => $operator, 'value' => $value];
        }
        return $this;
    }

    /**
     * Add whereIn condition.
     */
    public function whereIn(string $column, array $values): self
    {
        $this->whereConditions[] = ['type' => 'whereIn', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add whereNotIn condition.
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->whereConditions[] = ['type' => 'whereNotIn', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add whereBetween condition.
     */
    public function whereBetween(string $column, array $values): self
    {
        $this->whereConditions[] = ['type' => 'whereBetween', 'column' => $column, 'values' => $values];
        return $this;
    }

    /**
     * Add whereNull condition.
     */
    public function whereNull(string $column): self
    {
        $this->whereConditions[] = ['type' => 'whereNull', 'column' => $column];
        return $this;
    }

    /**
     * Add whereNotNull condition.
     */
    public function whereNotNull(string $column): self
    {
        $this->whereConditions[] = ['type' => 'whereNotNull', 'column' => $column];
        return $this;
    }

    /**
     * Specify exact IDs to target.
     */
    public function ids(array $ids): self
    {
        $this->targetIds = $ids;
        return $this;
    }

    /**
     * Add parameters for the action.
     */
    public function with(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    /**
     * Set batch size.
     */
    public function batchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Run synchronously instead of queued.
     */
    public function sync(): self
    {
        $this->shouldQueue = false;
        return $this;
    }

    /**
     * Run in queue (default).
     */
    public function queue(?string $connection = null, ?string $name = null): self
    {
        $this->shouldQueue = true;
        $this->queueConnection = $connection;
        $this->queueName = $name;
        return $this;
    }

    /**
     * Schedule for later execution.
     */
    public function scheduleFor(string|Carbon $datetime, ?string $timezone = null): self
    {
        $this->scheduledFor = $datetime instanceof Carbon ? $datetime : Carbon::parse($datetime);
        $this->scheduledTimezone = $timezone;
        return $this;
    }

    /**
     * Enable dry run mode.
     */
    public function dryRun(): self
    {
        $this->isDryRun = true;
        return $this;
    }

    /**
     * Enable undo functionality.
     */
    public function withUndo(int $expiryDays = 7): self
    {
        $this->withUndo = true;
        $this->undoExpiryDays = $expiryDays;
        return $this;
    }

    /**
     * Set custom authorization logic.
     */
    public function authorize(Closure $callback): self
    {
        $this->authorizationCallback = $callback;
        return $this;
    }

    /**
     * Register progress callback.
     */
    public function onProgress(Closure $callback): self
    {
        $this->progressCallback = $callback;
        return $this;
    }

    /**
     * Register completion callback.
     */
    public function onComplete(Closure $callback): self
    {
        $this->completeCallback = $callback;
        return $this;
    }

    /**
     * Register failure callback.
     */
    public function onFailure(Closure $callback): self
    {
        $this->failureCallback = $callback;
        return $this;
    }

    /**
     * Set the user for this action.
     */
    public function as(mixed $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Configure action chaining.
     */
    public function chain(array $actions): self
    {
        $this->chainConfig = $actions;
        return $this;
    }

    /**
     * Execute the bulk action.
     */
    public function execute(): BulkActionExecution
    {
        return $this->executor->execute($this);
    }

    /**
     * Get count of affected records without executing.
     */
    public function count(): int
    {
        return $this->buildQuery()->count();
    }

    /**
     * Get sample of affected records.
     */
    public function preview(int $limit = 10): Collection
    {
        $previewLimit = config('action-engine.dry_run.preview_limit', 100);
        $limit = min($limit, $previewLimit);

        return $this->buildQuery()->limit($limit)->get();
    }

    /**
     * Get details for dry run.
     */
    public function getDryRunDetails(): array
    {
        return [
            'total_count' => $this->count(),
            'preview' => $this->preview(),
            'action' => $this->actionName,
            'model' => $this->modelClass,
            'parameters' => $this->parameters,
            'filters' => $this->getSerializableFilters(),
        ];
    }

    /**
     * Build the query from filters.
     */
    public function buildQuery(): Builder
    {
        $query = $this->modelClass::query();
        // Include soft deleted records for restore action
        if ($this->actionName === 'restore' && method_exists($this->modelClass, 'bootSoftDeletes')) {
            $query->withTrashed();
        }
        // Apply specific IDs if provided
        if (!empty($this->targetIds)) {
            $query->whereIn($query->getModel()->getKeyName(), $this->targetIds);
        }

        // Apply WHERE conditions
        foreach ($this->whereConditions as $condition) {
            match ($condition['type']) {
                'basic' => $query->where($condition['column'], $condition['operator'], $condition['value']),
                'closure' => $query->where($condition['callback']),
                'whereIn' => $query->whereIn($condition['column'], $condition['values']),
                'whereNotIn' => $query->whereNotIn($condition['column'], $condition['values']),
                'whereBetween' => $query->whereBetween($condition['column'], $condition['values']),
                'whereNull' => $query->whereNull($condition['column']),
                'whereNotNull' => $query->whereNotNull($condition['column']),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Get serializable filters for storage.
     */
    public function getSerializableFilters(): array
    {
        $filters = [];

        if (!empty($this->targetIds)) {
            $filters['ids'] = $this->targetIds;
        }

        foreach ($this->whereConditions as $condition) {
            if ($condition['type'] !== 'closure') {
                $filters['where'][] = $condition;
            }
        }

        return $filters;
    }

    // Getters for executor access

    public function getModelClass(): ?string
    {
        return $this->modelClass;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize ?? config('action-engine.batch_size', 500);
    }

    public function shouldQueue(): bool
    {
        return $this->shouldQueue && !$this->isDryRun;
    }

    public function getQueueConnection(): ?string
    {
        return $this->queueConnection ?? config('action-engine.queue.connection');
    }

    public function getQueueName(): ?string
    {
        return $this->queueName ?? config('action-engine.queue.name', 'default');
    }

    public function getScheduledFor(): ?Carbon
    {
        return $this->scheduledFor;
    }

    public function getScheduledTimezone(): ?string
    {
        return $this->scheduledTimezone;
    }

    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    public function hasUndo(): bool
    {
        return $this->withUndo;
    }

    public function getUndoExpiryDays(): int
    {
        return $this->undoExpiryDays;
    }

    public function getAuthorizationCallback(): ?Closure
    {
        return $this->authorizationCallback;
    }

    public function getProgressCallback(): ?Closure
    {
        return $this->progressCallback;
    }

    public function getCompleteCallback(): ?Closure
    {
        return $this->completeCallback;
    }

    public function getFailureCallback(): ?Closure
    {
        return $this->failureCallback;
    }

    public function getUser(): mixed
    {
        return $this->user;
    }

    public function getChainConfig(): array
    {
        return $this->chainConfig;
    }

    public function getTargetIds(): array
    {
        return $this->targetIds;
    }

    public function getWhereConditions(): array
    {
        return $this->whereConditions;
    }
}
