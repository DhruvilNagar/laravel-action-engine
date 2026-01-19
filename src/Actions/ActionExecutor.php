<?php

namespace DhruvilNagar\ActionEngine\Actions;

use Closure;
use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Contracts\ProgressTrackerInterface;
use DhruvilNagar\ActionEngine\Contracts\UndoManagerInterface;
use DhruvilNagar\ActionEngine\Events\BulkActionCompleted;
use DhruvilNagar\ActionEngine\Events\BulkActionFailed;
use DhruvilNagar\ActionEngine\Events\BulkActionStarted;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException;
use DhruvilNagar\ActionEngine\Exceptions\UnauthorizedBulkActionException;
use DhruvilNagar\ActionEngine\Jobs\ProcessBulkActionBatch;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionProgress;
use DhruvilNagar\ActionEngine\Support\AuditLogger;
use DhruvilNagar\ActionEngine\Support\RateLimiter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ActionExecutor
{
    public function __construct(
        protected ActionRegistry $registry,
        protected ProgressTrackerInterface $progressTracker,
        protected UndoManagerInterface $undoManager,
        protected AuditLogger $auditLogger,
        protected RateLimiter $rateLimiter
    ) {}

    /**
     * Execute a bulk action from builder.
     */
    public function execute(BulkActionBuilder $builder): BulkActionExecution
    {
        // Validate the builder configuration
        $this->validate($builder);

        // Check authorization
        $this->authorize($builder);

        // Check rate limits
        $this->checkRateLimits($builder);

        // Handle dry run
        if ($builder->isDryRun()) {
            return $this->executeDryRun($builder);
        }

        // Handle scheduled execution
        if ($builder->getScheduledFor()) {
            return $this->scheduleExecution($builder);
        }

        // Create execution record
        $execution = $this->createExecution($builder);

        // Execute synchronously or queue
        if ($builder->shouldQueue()) {
            $this->dispatchBatches($execution, $builder);
        } else {
            $this->executeSync($execution, $builder);
        }

        return $execution;
    }

    /**
     * Validate the builder configuration.
     */
    protected function validate(BulkActionBuilder $builder): void
    {
        if (!$builder->getModelClass()) {
            throw new InvalidActionException('Model class is required.');
        }

        if (!class_exists($builder->getModelClass())) {
            throw new InvalidActionException("Model class '{$builder->getModelClass()}' does not exist.");
        }

        if (!$builder->getActionName()) {
            throw new InvalidActionException('Action name is required.');
        }

        if (!$this->registry->has($builder->getActionName())) {
            throw new InvalidActionException("Action '{$builder->getActionName()}' is not registered.");
        }
    }

    /**
     * Check authorization.
     */
    protected function authorize(BulkActionBuilder $builder): void
    {
        if (!config('action-engine.authorization.enabled', true)) {
            return;
        }

        // Custom authorization callback
        if ($callback = $builder->getAuthorizationCallback()) {
            if (!$callback($builder->getUser(), $builder)) {
                throw new UnauthorizedBulkActionException('You are not authorized to perform this action.');
            }
            return;
        }

        // Policy-based authorization
        if (config('action-engine.authorization.use_policies', true)) {
            $modelClass = $builder->getModelClass();
            $action = $builder->getActionName();
            $user = $builder->getUser();

            if ($user && Gate::getPolicyFor($modelClass)) {
                $ability = "bulk{$action}";
                if (!Gate::forUser($user)->allows($ability, $modelClass)) {
                    throw new UnauthorizedBulkActionException("You are not authorized to perform bulk {$action}.");
                }
            }
        }
    }

    /**
     * Check rate limits.
     */
    protected function checkRateLimits(BulkActionBuilder $builder): void
    {
        if (!config('action-engine.rate_limiting.enabled', true)) {
            return;
        }

        $user = $builder->getUser();
        if (!$user) {
            return;
        }

        if (!$this->rateLimiter->attempt($user)) {
            throw new RateLimitExceededException('Too many bulk actions. Please wait before trying again.');
        }

        // Check max records limit
        $count = $builder->count();
        $maxRecords = config('action-engine.rate_limiting.max_records_per_action', 100000);
        if ($count > $maxRecords) {
            throw new RateLimitExceededException("Cannot process more than {$maxRecords} records in a single action.");
        }
    }

    /**
     * Execute dry run.
     */
    protected function executeDryRun(BulkActionBuilder $builder): BulkActionExecution
    {
        $dryRunResults = $builder->getDryRunDetails();

        $execution = BulkActionExecution::create([
            'action_name' => $builder->getActionName(),
            'model_type' => $builder->getModelClass(),
            'filters' => $builder->getSerializableFilters(),
            'parameters' => $builder->getParameters(),
            'total_records' => $dryRunResults['total_count'],
            'processed_records' => 0,
            'failed_records' => 0,
            'status' => BulkActionExecution::STATUS_COMPLETED,
            'user_id' => $builder->getUser()?->getKey(),
            'user_type' => $builder->getUser() ? get_class($builder->getUser()) : null,
            'is_dry_run' => true,
            'dry_run_results' => array_merge($dryRunResults, [
                'count' => $dryRunResults['total_count'],
                'affected_count' => $dryRunResults['total_count'],
            ]),
            'completed_at' => now(),
        ]);

        return $execution;
    }

    /**
     * Schedule execution for later.
     */
    protected function scheduleExecution(BulkActionBuilder $builder): BulkActionExecution
    {
        $execution = BulkActionExecution::create([
            'action_name' => $builder->getActionName(),
            'model_type' => $builder->getModelClass(),
            'filters' => $builder->getSerializableFilters(),
            'parameters' => $builder->getParameters(),
            'total_records' => $builder->count(),
            'status' => BulkActionExecution::STATUS_SCHEDULED,
            'user_id' => $builder->getUser()?->getKey(),
            'user_type' => $builder->getUser() ? get_class($builder->getUser()) : null,
            'scheduled_for' => $builder->getScheduledFor(),
            'scheduled_timezone' => $builder->getScheduledTimezone(),
            'can_undo' => $builder->hasUndo(),
            'undo_expires_at' => $builder->hasUndo()
                ? $builder->getScheduledFor()->addDays($builder->getUndoExpiryDays())
                : null,
            'batch_size' => $builder->getBatchSize(),
            'queue_connection' => $builder->getQueueConnection(),
            'queue_name' => $builder->getQueueName(),
            'callbacks' => $this->serializeCallbacks($builder),
            'chain_config' => $builder->getChainConfig(),
        ]);

        return $execution;
    }

    /**
     * Create execution record.
     */
    protected function createExecution(BulkActionBuilder $builder): BulkActionExecution
    {
        $totalRecords = $builder->count();

        $execution = BulkActionExecution::create([
            'action_name' => $builder->getActionName(),
            'model_type' => $builder->getModelClass(),
            'filters' => $builder->getSerializableFilters(),
            'parameters' => $builder->getParameters(),
            'total_records' => $totalRecords,
            'status' => BulkActionExecution::STATUS_PENDING,
            'user_id' => $builder->getUser()?->getKey(),
            'user_type' => $builder->getUser() ? get_class($builder->getUser()) : null,
            'can_undo' => $builder->hasUndo(),
            'undo_expires_at' => $builder->hasUndo()
                ? now()->addDays($builder->getUndoExpiryDays())
                : null,
            'batch_size' => $builder->getBatchSize(),
            'queue_connection' => $builder->getQueueConnection(),
            'queue_name' => $builder->getQueueName(),
            'callbacks' => $this->serializeCallbacks($builder),
            'chain_config' => $builder->getChainConfig(),
        ]);

        return $execution;
    }

    /**
     * Dispatch batched jobs.
     */
    protected function dispatchBatches(BulkActionExecution $execution, BulkActionBuilder $builder): void
    {
        $batchSize = $builder->getBatchSize();
        $query = $builder->buildQuery();
        $keyName = $query->getModel()->getKeyName();

        // Get all IDs to process
        $allIds = $query->pluck($keyName)->toArray();
        $batches = array_chunk($allIds, $batchSize);

        // Initialize progress tracking
        $this->progressTracker->initialize($execution, count($batches), $batchSize);

        // Create batch progress records
        foreach ($batches as $batchNumber => $batchIds) {
            BulkActionProgress::create([
                'bulk_action_execution_id' => $execution->id,
                'batch_number' => $batchNumber + 1,
                'batch_size' => count($batchIds),
                'status' => BulkActionProgress::STATUS_PENDING,
            ]);
        }

        // Dispatch jobs
        $jobs = [];
        foreach ($batches as $batchNumber => $batchIds) {
            $jobs[] = new ProcessBulkActionBatch(
                $execution->uuid,
                $batchNumber + 1,
                $batchIds,
                $builder->getParameters(),
                $builder->hasUndo()
            );
        }

        // Use Laravel Bus batch if multiple jobs
        if (count($jobs) > 1) {
            Bus::batch($jobs)
                ->name("bulk-action-{$execution->uuid}")
                ->onQueue($builder->getQueueName())
                ->onConnection($builder->getQueueConnection())
                ->dispatch();
        } elseif (count($jobs) === 1) {
            dispatch($jobs[0])
                ->onQueue($builder->getQueueName())
                ->onConnection($builder->getQueueConnection());
        }

        // Mark as processing
        $execution->markAsStarted();

        // Dispatch started event
        event(new BulkActionStarted($execution));

        // Log to audit if enabled
        if (config('action-engine.audit.enabled', true)) {
            $this->auditLogger->logStarted($execution);
        }
    }

    /**
     * Execute bulk action synchronously in the current process.
     * 
     * This method processes records in batches within a database transaction,
     * capturing undo snapshots if enabled and tracking progress for each batch.
     * 
     * Note: We collect all records first before chunking to avoid issues with
     * soft deletes where records disappear during iteration (e.g., when deleting
     * records, the chunk() method would skip records as they get deleted).
     *
     * @param BulkActionExecution $execution The execution instance to process
     * @param BulkActionBuilder $builder The builder containing configuration
     * @return void
     * @throws \Throwable When action execution fails
     */
    protected function executeSync(BulkActionExecution $execution, BulkActionBuilder $builder): void
    {
        $execution->markAsStarted();
        event(new BulkActionStarted($execution));

        try {
            $query = $builder->buildQuery();
            $handler = $this->registry->get($builder->getActionName());
            $parameters = $builder->getParameters();
            $hasUndo = $builder->hasUndo();
            $processedCount = 0;
            $failedCount = 0;
            $batchNumber = 0;

            // Collect all records first to prevent soft delete iteration issues
            $allRecords = $query->get();
            $chunks = $allRecords->chunk($builder->getBatchSize());

            // Process all records within a single transaction for atomicity
            DB::transaction(function () use (
                $chunks,
                $handler,
                $parameters,
                $hasUndo,
                $execution,
                $builder,
                &$processedCount,
                &$failedCount,
                &$batchNumber
            ) {
                foreach ($chunks as $records) {
                    $batchNumber++;
                    $batchAffectedIds = [];
                    $batchFailedIds = [];

                    // Create progress tracking record for this batch
                    $progress = $execution->progress()->create([
                        'batch_number' => $batchNumber,
                        'batch_size' => $builder->getBatchSize(),
                        'total_in_batch' => $records->count(),
                        'status' => BulkActionProgress::STATUS_PROCESSING,
                        'started_at' => now(),
                    ]);

                    foreach ($records as $record) {
                        try {
                            // Capture snapshot for undo if enabled
                            if ($hasUndo) {
                                $this->captureUndoSnapshot($execution, $record, $handler);
                            }

                            // Execute the action
                            $this->executeOnRecord($handler, $record, $parameters);
                            $processedCount++;
                            $batchAffectedIds[] = $record->getKey();

                            // Update progress
                            $execution->incrementProcessed();

                            // Call progress callback
                            if ($callback = $builder->getProgressCallback()) {
                                $callback($execution->fresh()->progress_percentage, $execution);
                            }
                        } catch (\Throwable $e) {
                            $failedCount++;
                            $batchFailedIds[] = $record->getKey();
                            $execution->incrementFailed();
                        }
                    }

                    // Update progress record
                    $progress->update([
                        'status' => BulkActionProgress::STATUS_COMPLETED,
                        'processed_count' => count($batchAffectedIds),
                        'failed_count' => count($batchFailedIds),
                        'affected_ids' => $batchAffectedIds,
                        'failed_ids' => $batchFailedIds,
                        'completed_at' => now(),
                    ]);
                }
            });

            $execution->markAsCompleted();
            event(new BulkActionCompleted($execution));

            if ($callback = $builder->getCompleteCallback()) {
                $callback($execution);
            }

            if (config('action-engine.audit.enabled', true)) {
                $this->auditLogger->logCompleted($execution);
            }
        } catch (\Throwable $e) {
            $execution->markAsFailed(['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            event(new BulkActionFailed($execution, $e));

            if ($callback = $builder->getFailureCallback()) {
                $callback($e, $execution);
            }

            if (config('action-engine.audit.enabled', true)) {
                $this->auditLogger->logFailed($execution, $e);
            }

            throw $e;
        }
    }

    /**
     * Execute action on a single record.
     */
    public function executeOnRecord(Closure|ActionInterface $handler, Model $record, array $parameters): bool
    {
        if ($handler instanceof ActionInterface) {
            return $handler->execute($record, $parameters);
        }

        return $handler($record, $parameters);
    }

    /**
     * Capture undo snapshot for a record.
     */
    protected function captureUndoSnapshot(BulkActionExecution $execution, Model $record, Closure|ActionInterface $handler): void
    {
        $undoType = 'update';
        $fields = ['*'];

        if ($handler instanceof ActionInterface) {
            $undoType = $handler->getUndoType() ?? 'update';
            $fields = $handler->getUndoFields();
        }

        $this->undoManager->captureSnapshot($execution, $record, $undoType, $fields);
    }

    /**
     * Process a single batch (called from job).
     */
    public function processBatch(
        string $executionUuid,
        int $batchNumber,
        array $recordIds,
        array $parameters,
        bool $captureUndo
    ): void {
        $execution = BulkActionExecution::where('uuid', $executionUuid)->firstOrFail();
        $progress = $execution->progress()->where('batch_number', $batchNumber)->first();

        if (!$progress) {
            return;
        }

        $progress->markAsStarted();

        try {
            $handler = $this->registry->get($execution->action_name);
            $modelClass = $execution->model_type;
            $affectedIds = [];
            $failedIds = [];

            $records = $modelClass::whereIn(
                (new $modelClass)->getKeyName(),
                $recordIds
            )->get();

            foreach ($records as $record) {
                try {
                    if ($captureUndo) {
                        $this->captureUndoSnapshot($execution, $record, $handler);
                    }

                    $this->executeOnRecord($handler, $record, $parameters);
                    $affectedIds[] = $record->getKey();
                    $execution->incrementProcessed();
                } catch (\Throwable $e) {
                    $failedIds[] = $record->getKey();
                    $execution->incrementFailed();
                }
            }

            $progress->update([
                'status' => BulkActionProgress::STATUS_COMPLETED,
                'completed_at' => now(),
                'affected_ids' => $affectedIds,
                'failed_ids' => $failedIds,
                'processed_count' => count($affectedIds),
                'failed_count' => count($failedIds),
            ]);

            // Broadcast progress
            $this->progressTracker->broadcast($execution->fresh());

            // Check if all batches are complete
            $this->checkExecutionCompletion($execution);
        } catch (\Throwable $e) {
            $progress->markAsFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if execution is complete.
     */
    protected function checkExecutionCompletion(BulkActionExecution $execution): void
    {
        $pendingBatches = $execution->progress()
            ->whereIn('status', [BulkActionProgress::STATUS_PENDING, BulkActionProgress::STATUS_PROCESSING])
            ->count();

        if ($pendingBatches === 0) {
            $execution->markAsCompleted();
            event(new BulkActionCompleted($execution));

            if (config('action-engine.audit.enabled', true)) {
                $this->auditLogger->logCompleted($execution);
            }
        }
    }

    /**
     * Serialize callbacks for storage (we store references, not actual closures).
     */
    protected function serializeCallbacks(BulkActionBuilder $builder): ?array
    {
        // Note: Actual closures cannot be serialized
        // This is just for reference/logging
        return [
            'has_progress_callback' => $builder->getProgressCallback() !== null,
            'has_complete_callback' => $builder->getCompleteCallback() !== null,
            'has_failure_callback' => $builder->getFailureCallback() !== null,
        ];
    }
}
