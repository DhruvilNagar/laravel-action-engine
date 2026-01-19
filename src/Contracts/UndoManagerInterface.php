<?php

namespace DhruvilNagar\ActionEngine\Contracts;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionUndo;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for undo management implementations.
 */
interface UndoManagerInterface
{
    /**
     * Capture a snapshot of a record before modification.
     *
     * @param BulkActionExecution $execution
     * @param Model $record
     * @param string $actionType The type of action being performed
     * @param array $fields Fields to capture (or ['*'] for all)
     */
    public function captureSnapshot(
        BulkActionExecution $execution,
        Model $record,
        string $actionType,
        array $fields = ['*']
    ): void;

    /**
     * Undo an entire bulk action execution.
     *
     * @param BulkActionExecution $execution
     * @return int Number of records restored
     * @throws \DhruvilNagar\ActionEngine\Exceptions\UndoExpiredException
     */
    public function undo(BulkActionExecution $execution): int;

    /**
     * Undo a specific record.
     *
     * @param BulkActionUndo $undoRecord
     * @return bool
     */
    public function undoRecord(BulkActionUndo $undoRecord): bool;

    /**
     * Check if an execution can be undone.
     *
     * @param BulkActionExecution $execution
     * @return bool
     */
    public function canUndo(BulkActionExecution $execution): bool;

    /**
     * Get the remaining time until undo expires.
     *
     * @param BulkActionExecution $execution
     * @return string|null Human-readable time remaining, or null if expired
     */
    public function getTimeRemaining(BulkActionExecution $execution): ?string;

    /**
     * Clean up expired undo data.
     *
     * @return int Number of records cleaned up
     */
    public function cleanup(): int;

    /**
     * Restore a single record from its snapshot.
     *
     * @param BulkActionUndo $undoRecord
     */
    public function restoreRecord(BulkActionUndo $undoRecord): void;

    /**
     * Get the count of undoable records for an execution.
     *
     * @param BulkActionExecution $execution
     * @return int
     */
    public function getUndoableCount(BulkActionExecution $execution): int;
}
