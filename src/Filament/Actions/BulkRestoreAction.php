<?php

namespace DhruvilNagar\ActionEngine\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkRestoreAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_restore';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Restore Selected')
            ->color('success')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalHeading('Restore Selected Records')
            ->modalDescription('Restore the selected records.')
            ->action(function (Collection $records) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('restore')
                    ->ids($ids)
                    ->execute();

                Notification::make()
                    ->title('Bulk restore started')
                    ->body("Restoring {$execution->total_records} records.")
                    ->success()
                    ->send();

                return $execution;
            })
            ->deselectRecordsAfterCompletion();
    }
}
