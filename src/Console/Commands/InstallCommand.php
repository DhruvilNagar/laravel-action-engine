<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'action-engine:install 
                            {--force : Overwrite existing files}
                            {--all : Install all components}';

    /**
     * The console command description.
     */
    protected $description = 'Install the Action Engine package and configure frontend integrations';

    /**
     * Available frontend stacks.
     */
    protected array $frontendStacks = [
        'livewire' => 'Livewire (Laravel Livewire components)',
        'vue' => 'Vue.js (Composables and components)',
        'react' => 'React (Hooks and components)',
        'blade' => 'Blade (Traditional Blade views)',
        'filament' => 'Filament (Pre-built Filament Actions)',
        'alpine' => 'Alpine.js (Lightweight Alpine component)',
        'api' => 'API Only (No frontend components)',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════╗');
        $this->info('  ║     Laravel Action Engine Installer      ║');
        $this->info('  ╚══════════════════════════════════════════╝');
        $this->info('');

        // Publish configuration
        $this->publishConfig();

        // Publish migrations
        $this->publishMigrations();

        // Select frontend stacks
        $selectedStacks = $this->selectFrontendStacks();

        // Publish frontend assets
        $this->publishFrontendAssets($selectedStacks);

        // Ask about real-time progress
        if (!in_array('api', $selectedStacks)) {
            $this->configureRealTimeProgress();
        }

        // Run migrations
        $this->runMigrations();

        // Display success message
        $this->displaySuccessMessage($selectedStacks);

        return Command::SUCCESS;
    }

    /**
     * Publish package configuration.
     */
    protected function publishConfig(): void
    {
        $this->info('Publishing configuration...');

        $this->call('vendor:publish', [
            '--tag' => 'action-engine-config',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Publish package migrations.
     */
    protected function publishMigrations(): void
    {
        $this->info('Publishing migrations...');

        $this->call('vendor:publish', [
            '--tag' => 'action-engine-migrations',
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Select frontend stacks to install.
     */
    protected function selectFrontendStacks(): array
    {
        if ($this->option('all')) {
            return array_keys($this->frontendStacks);
        }

        $this->info('');
        $this->info('Select your frontend stack(s):');
        $this->info('');

        if (function_exists('\Laravel\Prompts\multiselect')) {
            return multiselect(
                label: 'Which frontend integrations would you like to install?',
                options: $this->frontendStacks,
                default: ['api'],
                hint: 'Use space to select, enter to confirm'
            );
        }

        // Fallback for older Laravel versions
        return $this->choice(
            'Which frontend integrations would you like to install?',
            array_keys($this->frontendStacks),
            null,
            null,
            true
        );
    }

    /**
     * Publish frontend assets based on selection.
     */
    protected function publishFrontendAssets(array $stacks): void
    {
        $this->info('');
        $this->info('Publishing frontend assets...');

        foreach ($stacks as $stack) {
            if ($stack === 'api') {
                continue; // No assets to publish for API only
            }

            $tag = "action-engine-{$stack}";
            
            $this->info("  → Publishing {$stack} components...");

            $this->call('vendor:publish', [
                '--tag' => $tag,
                '--force' => $this->option('force'),
            ]);

            // Special handling for Livewire
            if ($stack === 'livewire') {
                $this->publishLivewireComponents();
            }

            // Special handling for Filament
            if ($stack === 'filament') {
                $this->publishFilamentActions();
            }
        }
    }

    /**
     * Publish Livewire components.
     */
    protected function publishLivewireComponents(): void
    {
        $componentsPath = app_path('Livewire/ActionEngine');

        if (!File::isDirectory($componentsPath)) {
            File::makeDirectory($componentsPath, 0755, true);
        }

        // Copy the Livewire component class
        $componentContent = $this->getLivewireComponentContent();
        File::put("{$componentsPath}/BulkActionManager.php", $componentContent);
    }

    /**
     * Publish Filament Action classes.
     */
    protected function publishFilamentActions(): void
    {
        $actionsPath = app_path('Filament/Actions');

        if (!File::isDirectory($actionsPath)) {
            File::makeDirectory($actionsPath, 0755, true);
        }

        // Copy each Filament action
        $actions = ['BulkDeleteAction', 'BulkRestoreAction', 'BulkUpdateAction', 'BulkArchiveAction', 'BulkExportAction'];

        foreach ($actions as $action) {
            $content = $this->getFilamentActionContent($action);
            File::put("{$actionsPath}/{$action}.php", $content);
        }

        $this->info('    Created Filament Actions in app/Filament/Actions/');
    }

    /**
     * Configure real-time progress broadcasting.
     */
    protected function configureRealTimeProgress(): void
    {
        $this->info('');

        if (function_exists('\Laravel\Prompts\confirm')) {
            $needsRealTime = confirm(
                label: 'Do you need real-time progress updates via WebSocket?',
                default: false,
                hint: 'This requires Laravel Broadcasting to be configured'
            );
        } else {
            $needsRealTime = $this->confirm('Do you need real-time progress updates via WebSocket?', false);
        }

        if (!$needsRealTime) {
            $this->info('  → Using polling for progress updates (recommended)');
            return;
        }

        $this->info('');
        $this->info('Configuring real-time broadcasting...');

        // Ask for broadcast driver
        $drivers = [
            'pusher' => 'Pusher',
            'ably' => 'Ably',
            'reverb' => 'Laravel Reverb',
            'redis' => 'Redis',
        ];

        if (function_exists('\Laravel\Prompts\select')) {
            $driver = select(
                label: 'Which broadcast driver are you using?',
                options: $drivers,
                default: 'pusher'
            );
        } else {
            $driver = $this->choice('Which broadcast driver are you using?', array_keys($drivers), 0);
        }

        // Update the config file
        $configPath = config_path('action-engine.php');
        if (File::exists($configPath)) {
            $content = File::get($configPath);
            $content = str_replace(
                "'enabled' => env('ACTION_ENGINE_BROADCASTING_ENABLED', false)",
                "'enabled' => env('ACTION_ENGINE_BROADCASTING_ENABLED', true)",
                $content
            );
            File::put($configPath, $content);

            $this->info("  → Broadcasting enabled with {$driver} driver");
            $this->info('  → Remember to set BROADCAST_DRIVER=' . $driver . ' in your .env file');
        }
    }

    /**
     * Run migrations.
     */
    protected function runMigrations(): void
    {
        $this->info('');

        if (function_exists('\Laravel\Prompts\confirm')) {
            $runMigrations = confirm(
                label: 'Would you like to run the migrations now?',
                default: true
            );
        } else {
            $runMigrations = $this->confirm('Would you like to run the migrations now?', true);
        }

        if ($runMigrations) {
            $this->info('Running migrations...');
            $this->call('migrate');
        } else {
            $this->info('  → Run "php artisan migrate" when ready');
        }
    }

    /**
     * Display success message.
     */
    protected function displaySuccessMessage(array $stacks): void
    {
        $this->info('');
        $this->info('  ╔══════════════════════════════════════════╗');
        $this->info('  ║   Installation Complete! 🎉              ║');
        $this->info('  ╚══════════════════════════════════════════╝');
        $this->info('');
        $this->info('  Next steps:');
        $this->info('');
        $this->info('  1. Review config/action-engine.php for customization');
        $this->info('  2. Register custom actions in your AppServiceProvider:');
        $this->info('');
        $this->info('     use DhruvilNagar\ActionEngine\Facades\ActionRegistry;');
        $this->info('');
        $this->info('     ActionRegistry::register("my-action", function($record, $params) {');
        $this->info('         // Your custom logic');
        $this->info('     });');
        $this->info('');

        if (in_array('livewire', $stacks)) {
            $this->info('  3. Use Livewire component: <livewire:action-engine.bulk-action-manager />');
        }

        if (in_array('filament', $stacks)) {
            $this->info('  3. Add Filament actions to your resources:');
            $this->info('');
            $this->info('     use App\Filament\Actions\BulkDeleteAction;');
            $this->info('');
            $this->info('     ->bulkActions([');
            $this->info('         BulkDeleteAction::make(),');
            $this->info('     ])');
        }

        $this->info('');
        $this->info('  Documentation: https://github.com/dhruvilnagar/laravel-action-engine');
        $this->info('');
    }

    /**
     * Get Livewire component content.
     */
    protected function getLivewireComponentContent(): string
    {
        return <<<'PHP'
<?php

namespace App\Livewire\ActionEngine;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Support\UndoManager;
use Livewire\Component;

class BulkActionManager extends Component
{
    public array $selectedIds = [];
    public ?string $modelClass = null;
    public ?string $executionUuid = null;
    public ?BulkActionExecution $execution = null;
    public bool $showProgress = false;
    public int $pollInterval = 2000;

    protected $listeners = ['refreshProgress', 'executeAction'];

    public function mount(string $model, array $selectedIds = [])
    {
        $this->modelClass = $model;
        $this->selectedIds = $selectedIds;
    }

    public function executeAction(string $action, array $parameters = [], bool $withUndo = true)
    {
        if (empty($this->selectedIds)) {
            session()->flash('error', 'No records selected.');
            return;
        }

        $builder = BulkAction::on($this->modelClass)
            ->ids($this->selectedIds)
            ->action($action)
            ->with($parameters);

        if ($withUndo) {
            $builder->withUndo(days: 30);
        }

        $this->execution = $builder->execute();
        $this->executionUuid = $this->execution->uuid;
        $this->showProgress = true;

        $this->dispatch('start-polling');
    }

    public function refreshProgress()
    {
        if ($this->executionUuid) {
            $this->execution = BulkActionExecution::where('uuid', $this->executionUuid)->first();

            if ($this->execution && $this->execution->isFinished()) {
                $this->dispatch('stop-polling');
                $this->dispatch('action-completed', [
                    'status' => $this->execution->status,
                    'processed' => $this->execution->processed_records,
                    'failed' => $this->execution->failed_records,
                ]);
            }
        }
    }

    public function undoAction()
    {
        if ($this->execution && $this->execution->can_undo) {
            $undoManager = app(UndoManager::class);
            $restoredCount = $undoManager->undo($this->execution);

            $this->execution = $this->execution->fresh();
            session()->flash('message', "Successfully undone {$restoredCount} records.");
        }
    }

    public function cancelAction()
    {
        if ($this->execution && $this->execution->isInProgress()) {
            $this->execution->markAsCancelled();
            $this->execution = $this->execution->fresh();
            $this->dispatch('stop-polling');
            session()->flash('message', 'Action cancelled.');
        }
    }

    public function render()
    {
        return view('action-engine::livewire.bulk-action-manager');
    }
}
PHP;
    }

    /**
     * Get Filament action content.
     */
    protected function getFilamentActionContent(string $actionName): string
    {
        $baseContent = <<<'PHP'
<?php

namespace App\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;

class %ACTION_NAME% extends FilamentBulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('%LABEL%');
        $this->icon('%ICON%');
        $this->color('%COLOR%');
        $this->requiresConfirmation();

        $this->action(function (Collection $records) {
            $modelClass = get_class($records->first());
            $ids = $records->pluck('id')->toArray();

            $execution = BulkAction::on($modelClass)
                ->ids($ids)
                ->action('%ACTION_KEY%')
                ->withUndo(days: 30)
                ->execute();

            $this->success();
            
            return redirect()->back()->with('bulk_action_uuid', $execution->uuid);
        });
    }

    public static function getDefaultName(): ?string
    {
        return '%ACTION_KEY%';
    }
}
PHP;

        $replacements = match ($actionName) {
            'BulkDeleteAction' => [
                '%ACTION_NAME%' => 'BulkDeleteAction',
                '%LABEL%' => 'Delete Selected',
                '%ICON%' => 'heroicon-o-trash',
                '%COLOR%' => 'danger',
                '%ACTION_KEY%' => 'delete',
            ],
            'BulkRestoreAction' => [
                '%ACTION_NAME%' => 'BulkRestoreAction',
                '%LABEL%' => 'Restore Selected',
                '%ICON%' => 'heroicon-o-arrow-path',
                '%COLOR%' => 'success',
                '%ACTION_KEY%' => 'restore',
            ],
            'BulkUpdateAction' => [
                '%ACTION_NAME%' => 'BulkUpdateAction',
                '%LABEL%' => 'Update Selected',
                '%ICON%' => 'heroicon-o-pencil',
                '%COLOR%' => 'warning',
                '%ACTION_KEY%' => 'update',
            ],
            'BulkArchiveAction' => [
                '%ACTION_NAME%' => 'BulkArchiveAction',
                '%LABEL%' => 'Archive Selected',
                '%ICON%' => 'heroicon-o-archive-box',
                '%COLOR%' => 'gray',
                '%ACTION_KEY%' => 'archive',
            ],
            'BulkExportAction' => [
                '%ACTION_NAME%' => 'BulkExportAction',
                '%LABEL%' => 'Export Selected',
                '%ICON%' => 'heroicon-o-arrow-down-tray',
                '%COLOR%' => 'info',
                '%ACTION_KEY%' => 'export',
            ],
            default => [],
        };

        return str_replace(array_keys($replacements), array_values($replacements), $baseContent);
    }
}
