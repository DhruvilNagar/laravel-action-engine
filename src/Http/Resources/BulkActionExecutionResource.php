<?php

namespace DhruvilNagar\ActionEngine\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkActionExecutionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'action_name' => $this->action_name,
            'model_type' => $this->model_type,
            'status' => $this->status,
            'total_records' => $this->total_records,
            'processed_records' => $this->processed_records,
            'failed_records' => $this->failed_records,
            'progress_percentage' => $this->progress_percentage,
            'success_rate' => $this->success_rate,
            'can_undo' => $this->can_undo,
            'undo_expires_at' => $this->undo_expires_at?->toIso8601String(),
            'is_dry_run' => $this->is_dry_run,
            'dry_run_results' => $this->when($this->is_dry_run, $this->dry_run_results),
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'error_details' => $this->when(
                $this->status === 'failed',
                $this->error_details
            ),
            'filters' => $this->when($request->get('include_filters'), $this->filters),
            'parameters' => $this->when($request->get('include_parameters'), $this->parameters),
        ];
    }
}
