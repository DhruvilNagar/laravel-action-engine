<?php

namespace DhruvilNagar\ActionEngine\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkDeleteAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_delete';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Delete Selected')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->requiresConfirmation()
            ->modalHeading('Delete Selected Records')
            ->modalDescription('Are you sure you want to delete the selected records? This action can be undone within the configured time period.')
            ->modalSubmitActionLabel('Yes, delete them')
            ->action(function (Collection $records) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('delete')
                    ->ids($ids)
                    ->withUndo()
                    ->execute();

                Notification::make()
                    ->title('Bulk delete started')
                    ->body("Deleting {$execution->total_records} records. You can undo this action for {$this->getUndoExpiryDays()} days.")
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Progress')
                            ->url(route('filament.action-engine.progress', ['uuid' => $execution->uuid]))
                            ->markAsRead(),
                    ])
                    ->send();

                return $execution;
            })
            ->after(function () {
                // Refresh the table after action
                $this->getLivewire()->dispatch('refreshTable');
            })
            ->deselectRecordsAfterCompletion();
    }

    protected function getUndoExpiryDays(): int
    {
        return config('action-engine.undo.default_expiry_days', 7);
    }

    public function withUndo(int $days = null): static
    {
        $this->modalDescription = $this->modalDescription . ' You will be able to undo this action for ' . ($days ?? $this->getUndoExpiryDays()) . ' days.';
        
        return $this;
    }

    public function forceDelete(): static
    {
        $this->modalHeading('Permanently Delete Selected Records')
            ->modalDescription('Are you sure you want to permanently delete the selected records? This action CANNOT be undone.')
            ->action(function (Collection $records) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('delete')
                    ->ids($ids)
                    ->with(['force' => true])
                    ->execute();

                Notification::make()
                    ->title('Bulk permanent delete started')
                    ->body("Permanently deleting {$execution->total_records} records.")
                    ->warning()
                    ->send();

                return $execution;
            });

        return $this;
    }
}
