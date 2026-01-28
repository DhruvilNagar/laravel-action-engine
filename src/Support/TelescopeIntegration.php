<?php

namespace DhruvilNagar\ActionEngine\Support;

use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;

class TelescopeIntegration
{
    /**
     * Record bulk action execution in Telescope
     */
    public function recordExecution(BulkActionExecution $execution): void
    {
        if (!class_exists(Telescope::class)) {
            return;
        }

        Telescope::recordAction(IncomingEntry::make([
            'type' => 'bulk-action',
            'content' => [
                'execution_id' => $execution->id,
                'action_type' => $execution->action_type,
                'model_class' => $execution->model_class,
                'total_records' => $execution->total_records,
                'status' => $execution->status,
                'created_by' => $execution->created_by,
            ],
            'family_hash' => $execution->action_type,
        ]));
    }

    /**
     * Record bulk action progress
     */
    public function recordProgress(int $executionId, int $processed, int $total): void
    {
        if (!class_exists(Telescope::class)) {
            return;
        }

        Telescope::recordAction(IncomingEntry::make([
            'type' => 'bulk-action-progress',
            'content' => [
                'execution_id' => $executionId,
                'processed' => $processed,
                'total' => $total,
                'percentage' => round(($processed / $total) * 100, 2),
            ],
        ]));
    }

    /**
     * Tag Telescope entries with bulk action context
     */
    public function tagEntry(int $executionId): void
    {
        if (!class_exists(Telescope::class)) {
            return;
        }

        Telescope::tag(fn() => [
            'bulk-action',
            "execution:{$executionId}",
        ]);
    }
}
