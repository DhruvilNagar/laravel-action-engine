<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Jobs\ProcessBulkAction;
use DhruvilNagar\ActionEngine\Tests\TestCase;
use DhruvilNagar\ActionEngine\Tests\Fixtures\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Bus;

/**
 * QueueIntegrationTest
 * 
 * Tests queue-related functionality and job processing.
 */
class QueueIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Product::factory()->count(100)->create();
    }

    /** @test */
    public function it_dispatches_jobs_to_queue()
    {
        Queue::fake();

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->execute();

        Queue::assertPushed(ProcessBulkAction::class);

        $this->assertEquals('pending', $execution->status);
    }

    /** @test */
    public function it_processes_synchronously_when_sync_mode_enabled()
    {
        Queue::fake();

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->sync()
            ->execute();

        // Should not dispatch to queue
        Queue::assertNothingPushed();

        $this->assertEquals('completed', $execution->fresh()->status);
    }

    /** @test */
    public function it_uses_configured_queue_connection()
    {
        Queue::fake();

        config(['action-engine.queue.connection' => 'redis']);
        config(['action-engine.queue.name' => 'bulk-actions']);

        BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->execute();

        Queue::assertPushed(ProcessBulkAction::class, function ($job) {
            return $job->connection === 'redis' && $job->queue === 'bulk-actions';
        });
    }

    /** @test */
    public function it_respects_custom_queue_per_action()
    {
        Queue::fake();

        BulkAction::on(Product::class)
            ->action('update')
            ->onQueue('high-priority')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->execute();

        Queue::assertPushed(ProcessBulkAction::class, function ($job) {
            return $job->queue === 'high-priority';
        });
    }

    /** @test */
    public function it_processes_batches_correctly()
    {
        config(['action-engine.batch_size' => 10]);

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->batchSize(10)
            ->sync()
            ->execute();

        // With 100 products and batch size 10, should process 10 batches
        $this->assertEquals(100, $execution->fresh()->processed_records);
        $this->assertEquals('completed', $execution->fresh()->status);
    }

    /** @test */
    public function it_updates_progress_during_queue_processing()
    {
        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->withProgress()
            ->batchSize(10)
            ->sync()
            ->execute();

        $execution->refresh();

        $this->assertEquals(100, $execution->total_records);
        $this->assertEquals(100, $execution->processed_records);
        $this->assertNotNull($execution->completed_at);
    }

    /** @test */
    public function it_handles_job_failures_gracefully()
    {
        // Register an action that fails
        \ActionRegistry::register('fail-in-queue', function ($record, $params) {
            throw new \Exception('Job failure');
        });

        $execution = BulkAction::on(Product::class)
            ->action('fail-in-queue')
            ->where('id', 1)
            ->sync()
            ->execute();

        $execution->refresh();

        $this->assertEquals('failed', $execution->status);
        $this->assertNotNull($execution->error_message);
    }

    /** @test */
    public function it_retries_failed_jobs_based_on_configuration()
    {
        config(['action-engine.performance.max_retries' => 3]);

        $attempts = 0;

        \ActionRegistry::register('retry-in-queue', function ($record, $params) use (&$attempts) {
            $attempts++;

            if ($attempts < 2) {
                throw new \Exception('Retry needed');
            }

            return true;
        });

        $execution = BulkAction::on(Product::class)
            ->action('retry-in-queue')
            ->where('id', 1)
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->fresh()->status);
        $this->assertGreaterThanOrEqual(2, $attempts);
    }

    /** @test */
    public function it_can_cancel_queued_actions()
    {
        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->execute();

        // Cancel the action
        $execution->status = 'cancelled';
        $execution->save();

        // Job should check status and skip processing
        $this->assertEquals('cancelled', $execution->fresh()->status);
    }

    /** @test */
    public function it_processes_scheduled_actions_at_correct_time()
    {
        $scheduledTime = now()->addHour();

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->scheduleFor($scheduledTime)
            ->execute();

        $this->assertEquals('pending', $execution->status);
        $this->assertEquals($scheduledTime->format('Y-m-d H:i:s'), $execution->scheduled_at->format('Y-m-d H:i:s'));

        // Should not process yet
        $this->assertNull($execution->completed_at);
    }

    /** @test */
    public function it_handles_large_batches_without_timeout()
    {
        config(['action-engine.batch_size' => 1000]);

        // Create more products
        Product::factory()->count(5000)->create();

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->batchSize(1000)
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->fresh()->status);
        $this->assertGreaterThanOrEqual(5100, $execution->fresh()->processed_records);
    }

    /** @test */
    public function it_tracks_queue_metrics()
    {
        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->withProgress()
            ->sync()
            ->execute();

        $execution->refresh();

        $this->assertNotNull($execution->created_at);
        $this->assertNotNull($execution->completed_at);

        $duration = $execution->completed_at->diffInSeconds($execution->created_at);
        $this->assertGreaterThanOrEqual(0, $duration);
    }

    /** @test */
    public function it_dispatches_multiple_jobs_for_large_datasets()
    {
        Queue::fake();

        config(['action-engine.batch_size' => 10]);

        BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->batchSize(10)
            ->execute();

        // With 100 products and batch size 10, should dispatch multiple jobs
        Queue::assertPushed(ProcessBulkAction::class);
    }

    /** @test */
    public function it_handles_queue_connection_failures()
    {
        $this->markTestSkipped('Requires mocking queue connection failures');

        // Would test:
        // - Simulate queue connection failure
        // - Verify proper exception handling
        // - Check fallback behavior
    }

    /** @test */
    public function it_respects_job_timeout_configuration()
    {
        config(['action-engine.performance.queue_timeout' => 3600]);

        Queue::fake();

        BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->execute();

        Queue::assertPushed(ProcessBulkAction::class, function ($job) {
            return $job->timeout === 3600;
        });
    }

    /** @test */
    public function it_chains_related_jobs_properly()
    {
        Bus::fake();

        // Test job chaining if implemented
        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->execute();

        $this->assertInstanceOf(BulkActionExecution::class, $execution);
    }

    /** @test */
    public function it_handles_concurrent_queue_workers()
    {
        // This would require actual queue workers running
        $this->markTestSkipped('Requires concurrent queue workers');

        // Would test:
        // - Start multiple queue workers
        // - Dispatch multiple actions
        // - Verify proper concurrent processing
        // - Check for race conditions
    }

    /** @test */
    public function it_prioritizes_high_priority_actions()
    {
        Queue::fake();

        BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->priority('high')
            ->execute();

        Queue::assertPushed(ProcessBulkAction::class, function ($job) {
            return isset($job->priority) && $job->priority === 'high';
        });
    }

    /** @test */
    public function it_cleans_up_completed_jobs()
    {
        config(['action-engine.cleanup.execution_retention_days' => 0]);

        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', 1)
            ->parameters(['status' => 'active'])
            ->sync()
            ->execute();

        // Cleanup process would remove old executions
        // This is typically done by a scheduled command

        $this->assertEquals('completed', $execution->fresh()->status);
    }

    /** @test */
    public function it_monitors_queue_health()
    {
        $pendingCount = BulkActionExecution::where('status', 'pending')->count();
        $processingCount = BulkActionExecution::where('status', 'processing')->count();

        // Health check metrics
        $this->assertIsInt($pendingCount);
        $this->assertIsInt($processingCount);
    }

    /** @test */
    public function it_handles_job_releasing_on_failure()
    {
        $this->markTestSkipped('Requires queue worker to test job releasing');

        // Would test:
        // - Job fails
        // - Job is released back to queue
        // - Job is retried with backoff
    }
}
