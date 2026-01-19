<?php

namespace DhruvilNagar\ActionEngine;

use DhruvilNagar\ActionEngine\Actions\ActionExecutor;
use DhruvilNagar\ActionEngine\Actions\ActionRegistry;
use DhruvilNagar\ActionEngine\Actions\BuiltIn\ArchiveAction;
use DhruvilNagar\ActionEngine\Actions\BuiltIn\DeleteAction;
use DhruvilNagar\ActionEngine\Actions\BuiltIn\ExportAction;
use DhruvilNagar\ActionEngine\Actions\BuiltIn\RestoreAction;
use DhruvilNagar\ActionEngine\Actions\BuiltIn\UpdateAction;
use DhruvilNagar\ActionEngine\Console\Commands\CleanupCommand;
use DhruvilNagar\ActionEngine\Console\Commands\InstallCommand;
use DhruvilNagar\ActionEngine\Console\Commands\ListActionsCommand;
use DhruvilNagar\ActionEngine\Console\Commands\ProcessScheduledCommand;
use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Contracts\ExportDriverInterface;
use DhruvilNagar\ActionEngine\Contracts\ProgressTrackerInterface;
use DhruvilNagar\ActionEngine\Contracts\UndoManagerInterface;
use DhruvilNagar\ActionEngine\Support\AuditLogger;
use DhruvilNagar\ActionEngine\Support\ExportManager;
use DhruvilNagar\ActionEngine\Support\ProgressTracker;
use DhruvilNagar\ActionEngine\Support\RateLimiter;
use DhruvilNagar\ActionEngine\Support\SchedulerService;
use DhruvilNagar\ActionEngine\Support\UndoManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ActionEngineServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/action-engine.php', 'action-engine');

        $this->registerBindings();
        $this->registerFacades();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerMigrations();
        $this->registerViews();
        $this->registerBuiltInActions();
        $this->registerScheduledTasks();
    }

    /**
     * Register container bindings.
     */
    protected function registerBindings(): void
    {
        // Singleton bindings
        $this->app->singleton(ActionRegistry::class, function ($app) {
            return new ActionRegistry();
        });

        $this->app->singleton(ActionExecutor::class, function ($app) {
            return new ActionExecutor(
                $app->make(ActionRegistry::class),
                $app->make(ProgressTrackerInterface::class),
                $app->make(UndoManagerInterface::class),
                $app->make(AuditLogger::class),
                $app->make(RateLimiter::class)
            );
        });

        // Contract bindings
        $this->app->bind(ProgressTrackerInterface::class, ProgressTracker::class);
        $this->app->bind(UndoManagerInterface::class, UndoManager::class);

        // Support services
        $this->app->singleton(ProgressTracker::class);
        $this->app->singleton(UndoManager::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(RateLimiter::class);
        $this->app->singleton(SchedulerService::class);
        $this->app->singleton(ExportManager::class);
    }

    /**
     * Register facade accessors.
     */
    protected function registerFacades(): void
    {
        $this->app->bind('bulk-action', function ($app) {
            return new \DhruvilNagar\ActionEngine\Actions\BulkActionBuilder(
                $app->make(ActionExecutor::class)
            );
        });

        $this->app->bind('action-registry', function ($app) {
            return $app->make(ActionRegistry::class);
        });
    }

    /**
     * Register package publishing.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/../config/action-engine.php' => config_path('action-engine.php'),
            ], 'action-engine-config');

            // Migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'action-engine-migrations');

            // Views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/action-engine'),
            ], 'action-engine-views');

            // Livewire Components
            $this->publishes([
                __DIR__ . '/../resources/views/livewire' => resource_path('views/vendor/action-engine/livewire'),
                __DIR__ . '/../stubs/livewire' => app_path('Livewire/ActionEngine'),
            ], 'action-engine-livewire');

            // Vue Components
            $this->publishes([
                __DIR__ . '/../resources/js/vue' => resource_path('js/vendor/action-engine'),
            ], 'action-engine-vue');

            // React Components
            $this->publishes([
                __DIR__ . '/../resources/js/react' => resource_path('js/vendor/action-engine'),
            ], 'action-engine-react');

            // Alpine.js Component
            $this->publishes([
                __DIR__ . '/../resources/js/alpine' => resource_path('js/vendor/action-engine'),
            ], 'action-engine-alpine');

            // Filament Integration
            $this->publishes([
                __DIR__ . '/../stubs/filament' => app_path('Filament/Actions'),
            ], 'action-engine-filament');

            // Blade Components
            $this->publishes([
                __DIR__ . '/../resources/views/blade' => resource_path('views/vendor/action-engine/blade'),
            ], 'action-engine-blade');
        }
    }

    /**
     * Register console commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CleanupCommand::class,
                ProcessScheduledCommand::class,
                ListActionsCommand::class,
            ]);
        }
    }

    /**
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        if (! config('action-engine.routes.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register package migrations.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register package views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'action-engine');
    }

    /**
     * Register built-in actions.
     */
    protected function registerBuiltInActions(): void
    {
        $registry = $this->app->make(ActionRegistry::class);

        $registry->register('delete', DeleteAction::class);
        $registry->register('restore', RestoreAction::class);
        $registry->register('update', UpdateAction::class);
        $registry->register('archive', ArchiveAction::class);
        $registry->register('export', ExportAction::class);
    }

    /**
     * Register scheduled tasks.
     */
    protected function registerScheduledTasks(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Process scheduled actions
            if (config('action-engine.scheduling.enabled', true)) {
                $interval = config('action-engine.scheduling.check_interval_minutes', 1);
                $schedule->command('action-engine:process-scheduled')
                    ->everyMinute()
                    ->when(fn () => $interval === 1)
                    ->withoutOverlapping();
            }

            // Cleanup old data
            if (config('action-engine.cleanup.run_cleanup_on_schedule', true)) {
                $schedule->command('action-engine:cleanup')
                    ->daily()
                    ->at('02:00')
                    ->withoutOverlapping();
            }
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            ActionRegistry::class,
            ActionExecutor::class,
            ProgressTrackerInterface::class,
            UndoManagerInterface::class,
            'bulk-action',
            'action-registry',
        ];
    }
}
