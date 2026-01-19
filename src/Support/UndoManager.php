<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Contracts\UndoManagerInterface;
use DhruvilNagar\ActionEngine\Events\BulkActionUndone;
use DhruvilNagar\ActionEngine\Exceptions\UndoExpiredException;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Models\BulkActionUndo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * UndoManager
 * 
 * Manages undo functionality for bulk actions by capturing snapshots of records
 * before modification and providing restoration capabilities.
 * 
 * This service handles:
 * - Capturing original record state before actions
 * - Restoring records to their previous state
 * - Managing undo expiration and permissions
 * - Tracking undo operations for audit purposes
 */
class UndoManager implements UndoManagerInterface
{
    /**
     * Capture a snapshot of a record's state before modification.
     * 
     * Stores the original data in the undo table for potential restoration.
     * Supports capturing all fields or specific fields only.
     *
     * @param BulkActionExecution $execution The execution this snapshot belongs to
     * @param Model $record The model instance to capture
     * @param string $actionType The type of action being performed
     * @param array $fields Fields to capture (use ['*'] for all fields)
     * @return void
     */
    public function captureSnapshot(
        BulkActionExecution $execution,
        Model $record,
        string $actionType,
        array $fields = ['*']
    ): void {
        $originalData = $fields === ['*']
            ? $record->getOriginal()
            : array_intersect_key($record->getOriginal(), array_flip($fields));

        BulkActionUndo::create([
            'bulk_action_execution_id' => $execution->id,
            'model_type' => get_class($record),
            'model_id' => $record->getKey(),
            'original_data' => $originalData,
            'changes' => null, // Will be populated after action
            'undo_action_type' => $this->mapActionToUndoType($actionType),
        ]);
    }

    /**
     * Undo an entire bulk action execution.
     */
    public function undo(BulkActionExecution $execution): int
    {
        if (!$this->canUndo($execution)) {
            throw new UndoExpiredException();
        }

        $undoRecords = $execution->undoRecords()->notUndone()->get();
        $restoredCount = 0;

        DB::transaction(function () use ($undoRecords, &$restoredCount, $execution) {
            foreach ($undoRecords as $undoRecord) {
                try {
                    $this->restoreRecord($undoRecord);
                    $undoRecord->markAsUndone(auth()->id());
                    $restoredCount++;
                } catch (\Throwable $e) {
                    // Log error but continue with other records
                    report($e);
                }
            }

            // Mark execution as no longer undoable
            $execution->update([
                'can_undo' => false,
            ]);
        });

        // Mark audit entry as undone
        if (config('action-engine.audit.enabled', true)) {
            app(AuditLogger::class)->logUndone($execution);
        }

        event(new BulkActionUndone($execution, $restoredCount));

        return $restoredCount;
    }

    /**
     * Undo a specific record.
     */
    public function undoRecord(BulkActionUndo $undoRecord): bool
    {
        if ($undoRecord->isUndone()) {
            return false;
        }

        $this->restoreRecord($undoRecord);
        $undoRecord->markAsUndone(auth()->id());

        return true;
    }

    /**
     * Check if an execution can be undone.
     */
    public function canUndo(BulkActionExecution $execution): bool
    {
        return $execution->can_undo
            && $execution->undo_expires_at
            && $execution->undo_expires_at->isFuture()
            && $execution->status === BulkActionExecution::STATUS_COMPLETED
            && $execution->undoRecords()->notUndone()->exists();
    }

    /**
     * Get the remaining time until undo expires.
     */
    public function getTimeRemaining(BulkActionExecution $execution): ?string
    {
        if (!$execution->can_undo || !$execution->undo_expires_at) {
            return null;
        }

        if ($execution->undo_expires_at->isPast()) {
            return null;
        }

        return $execution->undo_expires_at->diffForHumans();
    }

    /**
     * Clean up expired undo data.
     */
    public function cleanup(): int
    {
        $expiredExecutions = BulkActionExecution::where('can_undo', true)
            ->where('undo_expires_at', '<', now())
            ->get();

        $cleanedCount = 0;

        foreach ($expiredExecutions as $execution) {
            $cleanedCount += $execution->undoRecords()->delete();
            $execution->update(['can_undo' => false]);
        }

        return $cleanedCount;
    }

    /**
     * Restore a single record from its snapshot.
     */
    public function restoreRecord(BulkActionUndo $undoRecord): void
    {
        $modelClass = $undoRecord->model_type;
        $modelId = $undoRecord->model_id;
        $originalData = $undoRecord->original_data;
        $undoType = $undoRecord->undo_action_type;

        switch ($undoType) {
            case BulkActionUndo::ACTION_RESTORE:
                // The record was deleted, so restore it (soft delete)
                $record = $modelClass::withTrashed()->find($modelId);
                if ($record && $this->usesSoftDeletes($record)) {
                    $record->restore();
                }
                break;

            case BulkActionUndo::ACTION_DELETE:
                // The record was restored, so delete it again
                $record = $modelClass::find($modelId);
                if ($record) {
                    $record->delete();
                }
                break;

            case BulkActionUndo::ACTION_UPDATE:
                // The record was updated, restore original values
                $record = $modelClass::withTrashed()->find($modelId);
                if ($record && $originalData) {
                    $record->fill($originalData);
                    $record->save();
                }
                break;

            case BulkActionUndo::ACTION_RECREATE:
                // The record was force deleted, recreate it
                if ($originalData) {
                    $record = new $modelClass();
                    $record->fill($originalData);
                    // Set primary key if it's not auto-incrementing
                    if (!$record->getIncrementing()) {
                        $record->{$record->getKeyName()} = $modelId;
                    }
                    $record->save();
                }
                break;
        }
    }

    /**
     * Get the count of undoable records for an execution.
     */
    public function getUndoableCount(BulkActionExecution $execution): int
    {
        return $execution->undoRecords()->notUndone()->count();
    }

    /**
     * Map action name to undo type.
     */
    protected function mapActionToUndoType(string $actionType): string
    {
        return match ($actionType) {
            'restore' => BulkActionUndo::ACTION_DELETE,
            'delete' => BulkActionUndo::ACTION_RESTORE,
            'recreate' => BulkActionUndo::ACTION_DELETE,
            default => BulkActionUndo::ACTION_UPDATE,
        };
    }

    /**
     * Check if model uses soft deletes.
     */
    protected function usesSoftDeletes(Model $record): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($record));
    }
}
