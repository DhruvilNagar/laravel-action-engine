<?php

namespace DhruvilNagar\ActionEngine\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkUpdateAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_update';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Update Selected')
            ->color('primary')
            ->icon('heroicon-o-pencil-square')
            ->requiresConfirmation()
            ->modalHeading('Update Selected Records')
            ->form([
                KeyValue::make('data')
                    ->label('Fields to Update')
                    ->keyLabel('Field Name')
                    ->valueLabel('New Value')
                    ->reorderable(false)
                    ->addActionLabel('Add Field')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('update')
                    ->ids($ids)
                    ->with(['data' => $data['data']])
                    ->withUndo()
                    ->execute();

                Notification::make()
                    ->title('Bulk update started')
                    ->body("Updating {$execution->total_records} records.")
                    ->success()
                    ->send();

                return $execution;
            })
            ->deselectRecordsAfterCompletion();
    }

    public function updateFields(array $fields): static
    {
        $this->form($fields)
            ->action(function (Collection $records, array $data) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('update')
                    ->ids($ids)
                    ->with(['data' => $data])
                    ->withUndo()
                    ->execute();

                Notification::make()
                    ->title('Bulk update started')
                    ->body("Updating {$execution->total_records} records.")
                    ->success()
                    ->send();

                return $execution;
            });

        return $this;
    }
}
