<?php

namespace DhruvilNagar\ActionEngine\Http\Controllers\Api;

use DhruvilNagar\ActionEngine\Contracts\ProgressTrackerInterface;
use DhruvilNagar\ActionEngine\Http\Resources\ProgressResource;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProgressController extends Controller
{
    public function __construct(
        protected ProgressTrackerInterface $progressTracker
    ) {}

    /**
     * Get progress for an execution.
     */
    public function show(string $uuid): JsonResponse
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();

        $details = $this->progressTracker->getDetails($execution);

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    /**
     * Get progress for multiple executions.
     */
    public function batch(array $uuids): JsonResponse
    {
        $executions = BulkActionExecution::whereIn('uuid', $uuids)->get();

        $progress = $executions->mapWithKeys(function ($execution) {
            return [$execution->uuid => $this->progressTracker->getDetails($execution)];
        });

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }
}
