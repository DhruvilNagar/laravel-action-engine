# Filament Integration Guide

## Installation

### 1. Install the Package

```bash
composer require dhruvilnagar/laravel-action-engine
```

### 2. Run the Installer

```bash
php artisan action-engine:install
```

When prompted, select "Filament" as one of your frontend stacks.

### 3. Publish Filament Assets (if needed)

```bash
php artisan vendor:publish --tag=action-engine-filament
```

## Basic Usage

### Adding Bulk Actions to a Resource

In your Filament Resource's `table()` method:

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkDeleteAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkArchiveAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkRestoreAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkExportAction;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Your columns
        ])
        ->bulkActions([
            BulkDeleteAction::make()
                ->withUndo(30), // Keep undo data for 30 days
                
            BulkArchiveAction::make(),
            
            BulkRestoreAction::make()
                ->visible(fn () => request()->routeIs('*.trashed')),
                
            BulkExportAction::make()
                ->withColumns(['id', 'name', 'email', 'created_at']),
        ]);
}
```

## Available Actions

### BulkDeleteAction

Soft delete or permanently delete records with undo support.

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkDeleteAction;

// Basic delete with undo
BulkDeleteAction::make()

// Delete with custom undo period
BulkDeleteAction::make()
    ->withUndo(60) // 60 days

// Force delete (permanent, no undo)
BulkDeleteAction::make()
    ->forceDelete()
```

### BulkArchiveAction

Archive records with optional reason.

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkArchiveAction;

BulkArchiveAction::make()
```

The action will show a form asking for an optional archive reason.

### BulkRestoreAction

Restore soft-deleted or archived records.

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkRestoreAction;

BulkRestoreAction::make()
    ->visible(fn () => request()->routeIs('*.trashed'))
```

### BulkUpdateAction

Update multiple fields across selected records.

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkUpdateAction;

// Using key-value pairs (default)
BulkUpdateAction::make()

// Using custom form fields
BulkUpdateAction::make()
    ->updateFields([
        Select::make('status')
            ->label('Status')
            ->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'suspended' => 'Suspended',
            ])
            ->required(),
            
        Toggle::make('verified')
            ->label('Email Verified'),
            
        DatePicker::make('expires_at')
            ->label('Expiration Date'),
    ])
```

### BulkExportAction

Export records to CSV, Excel, or PDF.

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkExportAction;

// Default export (shows format selector)
BulkExportAction::make()

// Export specific columns
BulkExportAction::make()
    ->withColumns([
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
    ])
```

## Custom Actions

### Creating a Custom Filament Bulk Action

```php
namespace App\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkSuspendAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_suspend';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Suspend Selected')
            ->color('warning')
            ->icon('heroicon-o-no-symbol')
            ->requiresConfirmation()
            ->modalHeading('Suspend Selected Users')
            ->modalDescription('Suspend the selected users. They will not be able to access the system.')
            ->form([
                Textarea::make('reason')
                    ->label('Suspension Reason')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (Collection $records, array $data) {
                $modelClass = $records->first()::class;
                $ids = $records->pluck('id')->toArray();

                $execution = BulkAction::on($modelClass)
                    ->action('suspend_user')
                    ->ids($ids)
                    ->with(['reason' => $data['reason']])
                    ->withUndo(days: 14)
                    ->execute();

                Notification::make()
                    ->title('Users suspended')
                    ->body("Suspending {$execution->total_records} users.")
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('undo')
                            ->label('Undo')
                            ->url(route('filament.action-engine.undo', ['uuid' => $execution->uuid]))
                            ->close(),
                    ])
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
```

Use it in your resource:

```php
use App\Filament\Actions\BulkSuspendAction;

public static function table(Table $table): Table
{
    return $table
        ->bulkActions([
            BulkSuspendAction::make(),
            // ... other actions
        ]);
}
```

## Progress Tracking

### Viewing Progress

Users will automatically see notifications with progress links:

```php
BulkDeleteAction::make()
```

When executed, shows a notification with a "View Progress" link.

### Custom Progress Page

Create a custom Filament page:

```php
namespace App\Filament\Pages;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Filament\Pages\Page;

class BulkActionProgress extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static string $view = 'filament.pages.bulk-action-progress';
    protected static ?string $title = 'Bulk Action Progress';
    
    public string $uuid;
    public ?BulkActionExecution $execution = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();
    }

    // Poll every 2 seconds
    protected function getRefreshInterval(): ?int
    {
        return $this->execution && !$this->execution->isComplete() ? 2000 : null;
    }

    public function refreshProgress(): void
    {
        $this->execution->refresh();
    }

    public function undoAction(): void
    {
        if ($this->execution->can_undo) {
            app(\DhruvilNagar\ActionEngine\Support\UndoManager::class)->undo($this->execution);
            $this->execution->refresh();
            
            Notification::make()
                ->title('Action undone')
                ->success()
                ->send();
        }
    }
}
```

Blade view (`resources/views/filament/pages/bulk-action-progress.blade.php`):

```blade
<x-filament::page>
    <div class="space-y-6">
        <x-filament::card>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium">
                        {{ ucfirst($execution->action_name) }} Progress
                    </h3>
                    <x-filament::badge :color="match($execution->status) {
                        'pending' => 'warning',
                        'processing' => 'primary',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'secondary',
                        default => 'secondary',
                    }">
                        {{ ucfirst($execution->status) }}
                    </x-filament::badge>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>Progress</span>
                        <span class="font-medium">{{ number_format($execution->progress_percentage, 1) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                        <div 
                            class="bg-primary-600 h-2.5 rounded-full transition-all duration-500"
                            style="width: {{ $execution->progress_percentage }}%"
                        ></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold">{{ $execution->processed_records }}</div>
                        <div class="text-xs text-gray-500">Processed</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">
                            {{ $execution->processed_records - $execution->failed_records }}
                        </div>
                        <div class="text-xs text-gray-500">Successful</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-600">{{ $execution->failed_records }}</div>
                        <div class="text-xs text-gray-500">Failed</div>
                    </div>
                </div>

                @if($execution->can_undo && $execution->status === 'completed')
                    <div class="pt-4 border-t">
                        <x-filament::button 
                            wire:click="undoAction" 
                            color="warning"
                            class="w-full"
                        >
                            Undo Action
                            <span class="ml-2 text-xs opacity-75">
                                (expires {{ $execution->undo_expires_at->diffForHumans() }})
                            </span>
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-filament::card>
    </div>
</x-filament::page>
```

## Advanced Configuration

### Customizing Action Appearance

```php
BulkDeleteAction::make()
    ->label('Remove Selected')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('Confirm Deletion')
    ->modalDescription('Are you sure? This action can be undone for 7 days.')
    ->modalIcon('heroicon-o-exclamation-triangle')
    ->modalIconColor('warning')
```

### Conditional Visibility

```php
BulkDeleteAction::make()
    ->visible(fn () => auth()->user()->can('delete_users'))

BulkArchiveAction::make()
    ->hidden(fn () => request()->routeIs('*.archived'))
```

### After Action Hooks

```php
BulkDeleteAction::make()
    ->after(function () {
        // Refresh the table
        $this->dispatch('refreshTable');
        
        // Clear cache
        Cache::tags(['users'])->flush();
    })
```

### Custom Notifications

```php
BulkDeleteAction::make()
    ->successNotificationTitle('Users Deleted')
    ->successNotification(
        Notification::make()
            ->success()
            ->title('Users deleted successfully')
            ->body('The selected users have been deleted.')
            ->persistent()
    )
```

## Undo Integration

### Adding Undo Button to Notifications

```php
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

Notification::make()
    ->title('Bulk action completed')
    ->success()
    ->persistent()
    ->actions([
        Action::make('undo')
            ->label('Undo')
            ->url(route('filament.action-engine.undo', ['uuid' => $execution->uuid]))
            ->close(),
        Action::make('view')
            ->label('View Details')
            ->url(route('filament.action-engine.progress', ['uuid' => $execution->uuid]))
            ->openUrlInNewTab(),
    ])
    ->send();
```

## Best Practices

### 1. Use Appropriate Colors

```php
BulkDeleteAction::make()->color('danger')
BulkArchiveAction::make()->color('warning')
BulkRestoreAction::make()->color('success')
BulkUpdateAction::make()->color('primary')
BulkExportAction::make()->color('info')
```

### 2. Always Require Confirmation for Destructive Actions

```php
BulkDeleteAction::make()
    ->requiresConfirmation()
    ->modalHeading('Delete Users')
    ->modalDescription('Are you sure you want to delete these users?')
```

### 3. Provide Clear Feedback

```php
BulkDeleteAction::make()
    ->successNotificationTitle('Users Deleted')
    ->successNotificationMessage(fn ($records) => 
        'Successfully deleted ' . $records->count() . ' users.'
    )
```

### 4. Enable Undo for Important Actions

```php
BulkDeleteAction::make()->withUndo(30) // 30 days
BulkUpdateAction::make() // Auto-includes undo
```

### 5. Use Visibility Rules

```php
BulkDeleteAction::make()
    ->visible(fn () => 
        auth()->user()->can('bulk_delete') && 
        !request()->routeIs('*.archived')
    )
```
