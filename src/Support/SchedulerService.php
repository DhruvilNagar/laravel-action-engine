<?php

namespace DhruvilNagar\ActionEngine\Support;

use Carbon\Carbon;
use DhruvilNagar\ActionEngine\Actions\ActionExecutor;
use DhruvilNagar\ActionEngine\Actions\BulkActionBuilder;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;

class SchedulerService
{
    public function __construct(
        protected ActionExecutor $executor
    ) {}

    /**
     * Process all due scheduled actions.
     */
    public function processDue(): int
    {
        $dueActions = BulkActionExecution::due()->get();
        $processedCount = 0;

        foreach ($dueActions as $execution) {
            try {
                $this->processScheduledAction($execution);
                $processedCount++;
            } catch (\Throwable $e) {
                $execution->markAsFailed([
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Process a single scheduled action.
     */
    public function processScheduledAction(BulkActionExecution $execution): void
    {
        // Rebuild the query from stored filters
        $modelClass = $execution->model_type;
        $query = $modelClass::query();

        // Apply stored filters
        $filters = $execution->filters ?? [];

        if (!empty($filters['ids'])) {
            $query->whereIn((new $modelClass)->getKeyName(), $filters['ids']);
        }

        if (!empty($filters['where'])) {
            foreach ($filters['where'] as $condition) {
                match ($condition['type']) {
                    'basic' => $query->where($condition['column'], $condition['operator'], $condition['value']),
                    'whereIn' => $query->whereIn($condition['column'], $condition['values']),
                    'whereNotIn' => $query->whereNotIn($condition['column'], $condition['values']),
                    'whereBetween' => $query->whereBetween($condition['column'], $condition['values']),
                    'whereNull' => $query->whereNull($condition['column']),
                    'whereNotNull' => $query->whereNotNull($condition['column']),
                    default => null,
                };
            }
        }

        // Update total records (might have changed since scheduling)
        $newTotal = $query->count();
        $execution->update([
            'total_records' => $newTotal,
            'status' => BulkActionExecution::STATUS_PENDING,
        ]);

        // Dispatch for processing
        $this->executor->dispatchFromExecution($execution, $query);
    }

    /**
     * Cancel a scheduled action.
     */
    public function cancel(BulkActionExecution $execution): bool
    {
        if ($execution->status !== BulkActionExecution::STATUS_SCHEDULED) {
            return false;
        }

        $execution->markAsCancelled();
        return true;
    }

    /**
     * Reschedule an action.
     */
    public function reschedule(BulkActionExecution $execution, Carbon $newDateTime): bool
    {
        if (!in_array($execution->status, [
            BulkActionExecution::STATUS_SCHEDULED,
            BulkActionExecution::STATUS_CANCELLED,
        ])) {
            return false;
        }

        $execution->update([
            'scheduled_for' => $newDateTime,
            'status' => BulkActionExecution::STATUS_SCHEDULED,
        ]);

        return true;
    }

    /**
     * Get all scheduled actions.
     */
    public function getScheduled(?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = BulkActionExecution::scheduled()
            ->orderBy('scheduled_for');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Get upcoming scheduled actions.
     */
    public function getUpcoming(int $hoursAhead = 24, ?int $userId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = BulkActionExecution::scheduled()
            ->where('scheduled_for', '<=', now()->addHours($hoursAhead))
            ->orderBy('scheduled_for');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }
}
