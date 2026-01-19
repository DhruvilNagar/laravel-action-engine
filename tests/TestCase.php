<?php

namespace DhruvilNagar\ActionEngine\Tests;

use DhruvilNagar\ActionEngine\ActionEngineServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'DhruvilNagar\\ActionEngine\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionEngineServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'BulkAction' => \DhruvilNagar\ActionEngine\Facades\BulkAction::class,
            'ActionRegistry' => \DhruvilNagar\ActionEngine\Facades\ActionRegistry::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('action-engine.batch_size', 10);
        $app['config']->set('action-engine.queue.connection', 'sync');
        $app['config']->set('action-engine.broadcasting.enabled', false);
        $app['config']->set('action-engine.rate_limiting.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        // Create a test model table
        $this->app['db']->connection()->getSchemaBuilder()->create('test_models', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('archived_at')->nullable();
            $table->string('archive_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
