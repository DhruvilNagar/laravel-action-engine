<?php

namespace DhruvilNagar\ActionEngine\Traits;

use DhruvilNagar\ActionEngine\Actions\BulkActionBuilder;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;

/**
 * Trait to add bulk action capabilities to Eloquent models.
 */
trait HasBulkActions
{
    /**
     * Start a bulk action builder for this model.
     */
    public static function bulkAction(string $action): BulkActionBuilder
    {
        return app(BulkActionBuilder::class)
            ->on(static::class)
            ->action($action);
    }

    /**
     * Bulk delete records.
     */
    public static function bulkDelete(array $ids, bool $force = false): BulkActionExecution
    {
        return static::bulkAction('delete')
            ->ids($ids)
            ->with(['force' => $force])
            ->withUndo()
            ->execute();
    }

    /**
     * Bulk restore soft-deleted records.
     */
    public static function bulkRestore(array $ids): BulkActionExecution
    {
        return static::bulkAction('restore')
            ->ids($ids)
            ->withUndo()
            ->execute();
    }

    /**
     * Bulk update records.
     */
    public static function bulkUpdate(array $ids, array $data): BulkActionExecution
    {
        return static::bulkAction('update')
            ->ids($ids)
            ->with(['data' => $data])
            ->withUndo()
            ->execute();
    }

    /**
     * Bulk archive records.
     */
    public static function bulkArchive(array $ids, ?string $reason = null): BulkActionExecution
    {
        $builder = static::bulkAction('archive')
            ->ids($ids)
            ->withUndo();

        if ($reason) {
            $builder->with(['reason' => $reason]);
        }

        return $builder->execute();
    }

    /**
     * Bulk export records.
     */
    public static function bulkExport(array $ids, string $format = 'csv', array $columns = ['*']): BulkActionExecution
    {
        return static::bulkAction('export')
            ->ids($ids)
            ->with([
                'format' => $format,
                'columns' => $columns,
            ])
            ->sync() // Exports typically run synchronously
            ->execute();
    }

    /**
     * Get bulk action executions for this model.
     */
    public static function getBulkActionHistory(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return BulkActionExecution::where('model_type', static::class)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get pending bulk actions for this model.
     */
    public static function getPendingBulkActions(): \Illuminate\Database\Eloquent\Collection
    {
        return BulkActionExecution::where('model_type', static::class)
            ->whereIn('status', [
                BulkActionExecution::STATUS_PENDING,
                BulkActionExecution::STATUS_PROCESSING,
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get undoable bulk actions for this model.
     */
    public static function getUndoableBulkActions(): \Illuminate\Database\Eloquent\Collection
    {
        return BulkActionExecution::where('model_type', static::class)
            ->undoable()
            ->orderByDesc('created_at')
            ->get();
    }
}
