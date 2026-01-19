<?php

namespace DhruvilNagar\ActionEngine\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkArchiveAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_archive';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Archive Selected')
            ->color('warning')
            ->icon('heroicon-o-archive-box')
            ->requiresConfirmation()
            ->modalHeading('Archive Selected Records')
            ->modalDescription('Archive the selected records. You can restore them later.')
            ->form([
                Textarea::make('reason')
                    ->label('Archive Reason')
                    ->placeholder('Enter the reason for archiving these records (optional)')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (Collection $records, array $data) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('archive')
                    ->ids($ids)
                    ->with(['reason' => $data['reason'] ?? null])
                    ->withUndo()
                    ->execute();

                Notification::make()
                    ->title('Bulk archive started')
                    ->body("Archiving {$execution->total_records} records.")
                    ->success()
                    ->send();

                return $execution;
            })
            ->deselectRecordsAfterCompletion();
    }
}
