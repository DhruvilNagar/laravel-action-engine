<?php

namespace DhruvilNagar\ActionEngine\Http\Controllers\Api;

use DhruvilNagar\ActionEngine\Actions\ActionExecutor;
use DhruvilNagar\ActionEngine\Actions\ActionRegistry;
use DhruvilNagar\ActionEngine\Actions\BulkActionBuilder;
use DhruvilNagar\ActionEngine\Events\BulkActionCancelled;
use DhruvilNagar\ActionEngine\Http\Requests\ExecuteBulkActionRequest;
use DhruvilNagar\ActionEngine\Http\Requests\PreviewBulkActionRequest;
use DhruvilNagar\ActionEngine\Http\Resources\BulkActionExecutionResource;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BulkActionController extends Controller
{
    public function __construct(
        protected ActionExecutor $executor,
        protected ActionRegistry $registry
    ) {}

    /**
     * List user's bulk actions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BulkActionExecution::query();

        // Filter by current user if authenticated
        if ($user = $request->user()) {
            $query->forUser($user);
        }

        // Apply filters
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($action = $request->get('action')) {
            $query->where('action_name', $action);
        }

        if ($model = $request->get('model')) {
            $query->where('model_type', $model);
        }

        $executions = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => BulkActionExecutionResource::collection($executions),
            'meta' => [
                'current_page' => $executions->currentPage(),
                'last_page' => $executions->lastPage(),
                'per_page' => $executions->perPage(),
                'total' => $executions->total(),
            ],
        ]);
    }

    /**
     * Execute a new bulk action.
     */
    public function execute(ExecuteBulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $builder = app(BulkActionBuilder::class)
            ->on($validated['model'])
            ->action($validated['action'])
            ->as($request->user());

        // Apply filters
        if (!empty($validated['filters'])) {
            $this->applyFilters($builder, $validated['filters']);
        }

        // Apply parameters
        if (!empty($validated['parameters'])) {
            $builder->with($validated['parameters']);
        }

        // Apply options
        if (!empty($validated['options'])) {
            $this->applyOptions($builder, $validated['options']);
        }

        $execution = $builder->execute();

        return response()->json([
            'success' => true,
            'data' => new BulkActionExecutionResource($execution),
            'message' => $execution->is_dry_run
                ? 'Dry run completed successfully.'
                : 'Bulk action initiated successfully.',
        ], 202);
    }

    /**
     * Get execution details.
     */
    public function show(string $uuid): JsonResponse
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new BulkActionExecutionResource($execution),
        ]);
    }

    /**
     * Cancel a pending or processing action.
     */
    public function cancel(string $uuid): JsonResponse
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();

        if (!$execution->isInProgress()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or processing actions can be cancelled.',
            ], 422);
        }

        $execution->markAsCancelled();
        
        event(new BulkActionCancelled($execution));

        if (config('action-engine.audit.enabled', true)) {
            app(AuditLogger::class)->logCancelled($execution);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk action cancelled successfully.',
            'data' => new BulkActionExecutionResource($execution->fresh()),
        ]);
    }

    /**
     * Preview action (dry run).
     */
    public function preview(PreviewBulkActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $builder = app(BulkActionBuilder::class)
            ->on($validated['model'])
            ->action($validated['action'])
            ->dryRun()
            ->as($request->user());

        // Apply filters
        if (!empty($validated['filters'])) {
            $this->applyFilters($builder, $validated['filters']);
        }

        // Apply parameters
        if (!empty($validated['parameters'])) {
            $builder->with($validated['parameters']);
        }

        $details = $builder->getDryRunDetails();
        $previewLimit = $validated['preview_limit'] ?? 10;
        $details['preview'] = $builder->preview($previewLimit);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Get available actions.
     */
    public function actions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->registry->allWithMetadata(),
        ]);
    }

    /**
     * Apply filters to builder.
     */
    protected function applyFilters(BulkActionBuilder $builder, array $filters): void
    {
        // Apply IDs filter
        if (!empty($filters['ids'])) {
            $builder->ids($filters['ids']);
        }

        // Apply WHERE conditions
        if (!empty($filters['where'])) {
            foreach ($filters['where'] as $condition) {
                if (is_array($condition) && count($condition) >= 2) {
                    $column = $condition[0];
                    $operator = count($condition) === 3 ? $condition[1] : '=';
                    $value = count($condition) === 3 ? $condition[2] : $condition[1];
                    $builder->where($column, $operator, $value);
                }
            }
        }

        // Apply whereIn conditions
        if (!empty($filters['where_in'])) {
            foreach ($filters['where_in'] as $condition) {
                if (is_array($condition) && count($condition) === 2) {
                    $builder->whereIn($condition[0], $condition[1]);
                }
            }
        }

        // Apply whereNotIn conditions
        if (!empty($filters['where_not_in'])) {
            foreach ($filters['where_not_in'] as $condition) {
                if (is_array($condition) && count($condition) === 2) {
                    $builder->whereNotIn($condition[0], $condition[1]);
                }
            }
        }

        // Apply whereBetween conditions
        if (!empty($filters['where_between'])) {
            foreach ($filters['where_between'] as $condition) {
                if (is_array($condition) && count($condition) === 2) {
                    $builder->whereBetween($condition[0], $condition[1]);
                }
            }
        }
    }

    /**
     * Apply options to builder.
     */
    protected function applyOptions(BulkActionBuilder $builder, array $options): void
    {
        if (!empty($options['batch_size'])) {
            $builder->batchSize((int) $options['batch_size']);
        }

        if (!empty($options['with_undo'])) {
            $expiryDays = $options['undo_expiry_days'] ?? 7;
            $builder->withUndo((int) $expiryDays);
        }

        if (!empty($options['sync'])) {
            $builder->sync();
        }

        if (!empty($options['schedule_for'])) {
            $builder->scheduleFor(
                $options['schedule_for'],
                $options['schedule_timezone'] ?? null
            );
        }

        if (!empty($options['dry_run'])) {
            $builder->dryRun();
        }
    }
}
