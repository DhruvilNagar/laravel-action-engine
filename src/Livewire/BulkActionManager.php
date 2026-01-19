<?php

namespace DhruvilNagar\ActionEngine\Livewire;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Support\UndoManager;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BulkActionManager extends Component
{
    /**
     * Selected record IDs.
     */
    public array $selectedIds = [];

    /**
     * Model class to perform actions on.
     */
    public string $modelClass;

    /**
     * Current execution UUID.
     */
    public ?string $executionUuid = null;

    /**
     * Current execution.
     */
    public ?BulkActionExecution $execution = null;

    /**
     * Available actions.
     */
    public array $availableActions = [];

    /**
     * Show progress modal.
     */
    public bool $showProgressModal = false;

    /**
     * Show confirmation modal.
     */
    public bool $showConfirmModal = false;

    /**
     * Pending action details.
     */
    public ?string $pendingAction = null;
    public array $pendingParameters = [];

    /**
     * Error message.
     */
    public ?string $error = null;

    /**
     * Success message.
     */
    public ?string $success = null;

    /**
     * Auto-refresh progress.
     */
    public bool $autoRefresh = true;

    /**
     * Listeners.
     */
    protected $listeners = [
        'refreshProgress',
        'closeModals',
    ];

    /**
     * Mount the component.
     */
    public function mount(string $modelClass, array $availableActions = [], bool $autoRefresh = true): void
    {
        $this->modelClass = $modelClass;
        $this->availableActions = $availableActions;
        $this->autoRefresh = $autoRefresh;

        // If no actions provided, use default actions
        if (empty($this->availableActions)) {
            $this->availableActions = [
                'delete' => [
                    'label' => 'Delete',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirmation' => 'Are you sure you want to delete the selected records?',
                ],
                'archive' => [
                    'label' => 'Archive',
                    'icon' => 'archive',
                    'color' => 'warning',
                    'confirmation' => 'Are you sure you want to archive the selected records?',
                ],
                'restore' => [
                    'label' => 'Restore',
                    'icon' => 'refresh',
                    'color' => 'success',
                    'confirmation' => null,
                ],
            ];
        }
    }

    /**
     * Execute a bulk action.
     */
    public function executeAction(string $action, array $parameters = [], bool $withUndo = true): void
    {
        $this->resetMessages();

        // Validation
        if (empty($this->selectedIds)) {
            $this->error = 'Please select at least one record.';
            return;
        }

        // Check if confirmation is required
        $actionConfig = $this->availableActions[$action] ?? null;
        if ($actionConfig && isset($actionConfig['confirmation']) && $actionConfig['confirmation']) {
            $this->pendingAction = $action;
            $this->pendingParameters = $parameters;
            $this->showConfirmModal = true;
            return;
        }

        $this->performAction($action, $parameters, $withUndo);
    }

    /**
     * Confirm and execute the pending action.
     */
    public function confirmAction(): void
    {
        if ($this->pendingAction) {
            $this->showConfirmModal = false;
            $this->performAction($this->pendingAction, $this->pendingParameters, true);
            $this->pendingAction = null;
            $this->pendingParameters = [];
        }
    }

    /**
     * Cancel the pending action.
     */
    public function cancelAction(): void
    {
        $this->showConfirmModal = false;
        $this->pendingAction = null;
        $this->pendingParameters = [];
    }

    /**
     * Perform the bulk action.
     */
    protected function performAction(string $action, array $parameters, bool $withUndo): void
    {
        try {
            $builder = BulkAction::on($this->modelClass)
                ->action($action)
                ->ids($this->selectedIds)
                ->with($parameters);

            if ($withUndo) {
                $builder->withUndo();
            }

            $this->execution = $builder->execute();
            $this->executionUuid = $this->execution->uuid;
            $this->showProgressModal = true;

            // Start auto-refresh if enabled
            if ($this->autoRefresh) {
                $this->dispatch('start-progress-polling');
            }

            $this->success = "Bulk action '{$action}' started successfully.";
        } catch (\Exception $e) {
            $this->error = "Failed to execute action: {$e->getMessage()}";
        }
    }

    /**
     * Delete selected records.
     */
    public function bulkDelete(bool $forceDelete = false): void
    {
        $this->executeAction('delete', ['force' => $forceDelete], true);
    }

    /**
     * Update selected records.
     */
    public function bulkUpdate(array $data): void
    {
        $this->executeAction('update', ['data' => $data], true);
    }

    /**
     * Archive selected records.
     */
    public function bulkArchive(string $reason = ''): void
    {
        $this->executeAction('archive', ['reason' => $reason], true);
    }

    /**
     * Restore selected records.
     */
    public function bulkRestore(): void
    {
        $this->executeAction('restore', [], false);
    }

    /**
     * Export selected records.
     */
    public function bulkExport(string $format = 'csv', array $columns = ['*']): void
    {
        $this->executeAction('export', [
            'format' => $format,
            'columns' => $columns,
        ], false);
    }

    /**
     * Undo the current action.
     */
    public function undoAction(): void
    {
        $this->resetMessages();

        if (!$this->execution || !$this->execution->can_undo) {
            $this->error = 'This action cannot be undone.';
            return;
        }

        try {
            app(UndoManager::class)->undo($this->execution);
            $this->execution->refresh();
            $this->success = 'Action undone successfully.';
            
            // Emit event to refresh parent component
            $this->dispatch('action-undone', uuid: $this->execution->uuid);
        } catch (\Exception $e) {
            $this->error = "Failed to undo action: {$e->getMessage()}";
        }
    }

    /**
     * Cancel the current execution.
     */
    public function cancelExecution(): void
    {
        $this->resetMessages();

        if (!$this->execution) {
            $this->error = 'No execution to cancel.';
            return;
        }

        if (!in_array($this->execution->status, ['pending', 'processing'])) {
            $this->error = 'This execution cannot be cancelled.';
            return;
        }

        try {
            $this->execution->cancel();
            $this->success = 'Execution cancelled successfully.';
        } catch (\Exception $e) {
            $this->error = "Failed to cancel execution: {$e->getMessage()}";
        }
    }

    /**
     * Refresh progress.
     */
    public function refreshProgress(): void
    {
        if ($this->executionUuid) {
            $this->execution = BulkActionExecution::where('uuid', $this->executionUuid)->first();

            // Stop auto-refresh if completed
            if ($this->execution && $this->execution->isComplete()) {
                $this->dispatch('stop-progress-polling');

                // Clear selected IDs on success
                if ($this->execution->status === 'completed') {
                    $this->selectedIds = [];
                }
            }
        }
    }

    /**
     * Close all modals.
     */
    public function closeModals(): void
    {
        $this->showProgressModal = false;
        $this->showConfirmModal = false;
    }

    /**
     * Reset messages.
     */
    protected function resetMessages(): void
    {
        $this->error = null;
        $this->success = null;
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageProperty(): float
    {
        return $this->execution?->progress_percentage ?? 0;
    }

    /**
     * Check if execution is in progress.
     */
    public function getIsInProgressProperty(): bool
    {
        return $this->execution && in_array($this->execution->status, ['pending', 'processing']);
    }

    /**
     * Check if execution is complete.
     */
    public function getIsCompleteProperty(): bool
    {
        return $this->execution && in_array($this->execution->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('action-engine::livewire.bulk-action-manager');
    }
}
