<?php

namespace DhruvilNagar\ActionEngine\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkExportAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Export Selected')
            ->color('info')
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                Select::make('format')
                    ->label('Export Format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'Excel (XLSX)',
                        'pdf' => 'PDF',
                    ])
                    ->default('csv')
                    ->required(),
                TextInput::make('filename')
                    ->label('Filename (optional)')
                    ->placeholder('export_' . date('Y-m-d_His'))
                    ->maxLength(255),
            ])
            ->action(function (Collection $records, array $data) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('export')
                    ->ids($ids)
                    ->with([
                        'format' => $data['format'],
                        'filename' => $data['filename'] ?? 'export_' . date('Y-m-d_His'),
                        'columns' => ['*'], // Export all columns by default
                    ])
                    ->execute();

                Notification::make()
                    ->title('Bulk export started')
                    ->body("Exporting {$execution->total_records} records to {$data['format']}.")
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
            ->deselectRecordsAfterCompletion();
    }

    public function withColumns(array $columns): static
    {
        $this->action(function (Collection $records, array $data) use ($columns) {
            $modelClass = $records->first()::class;
            $ids = $records->pluck('id')->toArray();

            $execution = BulkAction::on($modelClass)
                ->action('export')
                ->ids($ids)
                ->with([
                    'format' => $data['format'],
                    'filename' => $data['filename'] ?? 'export_' . date('Y-m-d_His'),
                    'columns' => $columns,
                ])
                ->execute();

            Notification::make()
                ->title('Bulk export started')
                ->body("Exporting {$execution->total_records} records.")
                ->success()
                ->send();

            return $execution;
        });

        return $this;
    }
}
