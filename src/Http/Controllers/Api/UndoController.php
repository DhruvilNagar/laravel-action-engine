<?php

namespace DhruvilNagar\ActionEngine\Http\Controllers\Api;

use DhruvilNagar\ActionEngine\Contracts\UndoManagerInterface;
use DhruvilNagar\ActionEngine\Http\Resources\BulkActionExecutionResource;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class UndoController extends Controller
{
    public function __construct(
        protected UndoManagerInterface $undoManager
    ) {}

    /**
     * Undo a bulk action.
     */
    public function undo(string $uuid): JsonResponse
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();

        if (!$this->undoManager->canUndo($execution)) {
            return response()->json([
                'success' => false,
                'message' => 'This action cannot be undone. It may have already been undone or the undo period has expired.',
            ], 422);
        }

        $restoredCount = $this->undoManager->undo($execution);

        return response()->json([
            'success' => true,
            'message' => "Successfully undone {$restoredCount} records.",
            'data' => [
                'restored_count' => $restoredCount,
                'execution' => new BulkActionExecutionResource($execution->fresh()),
            ],
        ]);
    }

    /**
     * Check if an action can be undone.
     */
    public function check(string $uuid): JsonResponse
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();

        $canUndo = $this->undoManager->canUndo($execution);
        $timeRemaining = $this->undoManager->getTimeRemaining($execution);
        $undoableCount = $this->undoManager->getUndoableCount($execution);

        return response()->json([
            'success' => true,
            'data' => [
                'can_undo' => $canUndo,
                'time_remaining' => $timeRemaining,
                'undoable_count' => $undoableCount,
                'expires_at' => $execution->undo_expires_at?->toIso8601String(),
            ],
        ]);
    }
}
