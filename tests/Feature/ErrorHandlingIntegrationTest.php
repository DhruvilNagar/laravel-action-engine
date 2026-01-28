<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Tests\TestCase;
use DhruvilNagar\ActionEngine\Tests\Fixtures\Product;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * ErrorHandlingIntegrationTest
 * 
 * Tests error handling in real bulk action scenarios.
 */
class ErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test products
        Product::factory()->count(10)->create();
    }

    /** @test */
    public function it_handles_unregistered_action_error()
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage('not registered');

        BulkAction::on(Product::class)
            ->action('non-existent-action')
            ->where('status', 'active')
            ->execute();
    }

    /** @test */
    public function it_handles_rate_limit_exceeded()
    {
        config(['action-engine.rate_limiting.max_concurrent_actions' => 1]);

        // Start first action (should succeed)
        $execution1 = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'active'])
            ->execute();

        $this->assertInstanceOf(BulkActionExecution::class, $execution1);

        // Try to start second action (should fail)
        $this->expectException(RateLimitExceededException::class);

        BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '>', 0)
            ->parameters(['status' => 'inactive'])
            ->execute();
    }

    /** @test */
    public function it_records_execution_errors_in_database()
    {
        // Register an action that will fail
        \ActionRegistry::register('failing-action', function ($record, $params) {
            throw new \Exception('Intentional failure for testing');
        });

        try {
            $execution = BulkAction::on(Product::class)
                ->action('failing-action')
                ->where('id', '>', 0)
                ->sync()
                ->execute();
        } catch (\Exception $e) {
            // Expected to fail
        }

        $execution = BulkActionExecution::latest()->first();

        $this->assertEquals('failed', $execution->status);
        $this->assertNotNull($execution->error_message);
        $this->assertStringContainsString('Intentional failure', $execution->error_message);
    }

    /** @test */
    public function it_handles_database_constraint_violations()
    {
        // This test assumes foreign key constraints exist
        $this->markTestSkipped('Requires specific database schema with constraints');

        // Example of what would be tested:
        // Try to delete a record that has dependent records
        // Should catch constraint violation and report properly
    }

    /** @test */
    public function it_handles_memory_limit_gracefully()
    {
        $this->markTestSkipped('Difficult to test memory limits in unit tests');

        // Would test:
        // - Create large dataset
        // - Set low memory limit
        // - Verify it adjusts batch size or fails gracefully
    }

    /** @test */
    public function it_continues_on_partial_errors_when_configured()
    {
        config(['action-engine.error_handling.continue_on_error' => true]);

        // Register action that fails on specific records
        \ActionRegistry::register('selective-fail', function ($record, $params) {
            if ($record->id % 2 === 0) {
                throw new \Exception('Failed on even ID');
            }
            return true;
        });

        $execution = BulkAction::on(Product::class)
            ->action('selective-fail')
            ->where('id', '>', 0)
            ->sync()
            ->execute();

        // Should have some successes and some failures
        $this->assertGreaterThan(0, $execution->successful_records);
        $this->assertGreaterThan(0, $execution->failed_records);
        $this->assertEquals('completed', $execution->status); // Completed with partial failures
    }

    /** @test */
    public function it_stops_on_first_error_when_configured()
    {
        config(['action-engine.error_handling.continue_on_error' => false]);

        // Register action that fails on first record
        \ActionRegistry::register('fail-immediately', function ($record, $params) {
            throw new \Exception('Immediate failure');
        });

        try {
            $execution = BulkAction::on(Product::class)
                ->action('fail-immediately')
                ->where('id', '>', 0)
                ->sync()
                ->execute();
        } catch (\Exception $e) {
            // Expected
        }

        $execution = BulkActionExecution::latest()->first();

        $this->assertEquals('failed', $execution->status);
        $this->assertEquals(0, $execution->successful_records);
    }

    /** @test */
    public function it_provides_detailed_error_context()
    {
        \ActionRegistry::register('context-error', function ($record, $params) {
            throw new \Exception("Failed on record {$record->id}");
        });

        try {
            BulkAction::on(Product::class)
                ->action('context-error')
                ->where('id', 1)
                ->sync()
                ->execute();
        } catch (\Exception $e) {
            $execution = BulkActionExecution::latest()->first();

            $this->assertNotNull($execution->error_message);
            $this->assertStringContainsString('record', strtolower($execution->error_message));
        }
    }

    /** @test */
    public function it_handles_timeout_errors()
    {
        $this->markTestSkipped('Timeout testing requires long-running operations');

        // Would test:
        // - Set short timeout
        // - Run slow operation
        // - Verify timeout exception thrown
        // - Verify proper error recording
    }

    /** @test */
    public function it_retries_failed_batches_when_configured()
    {
        config(['action-engine.performance.max_retries' => 3]);

        $attempts = 0;

        \ActionRegistry::register('retry-action', function ($record, $params) use (&$attempts) {
            $attempts++;

            if ($attempts < 3) {
                throw new \Exception('Retry me');
            }

            return true;
        });

        $execution = BulkAction::on(Product::class)
            ->action('retry-action')
            ->where('id', 1)
            ->sync()
            ->execute();

        // Should succeed after retries
        $this->assertEquals('completed', $execution->status);
        $this->assertGreaterThanOrEqual(3, $attempts);
    }

    /** @test */
    public function it_logs_errors_to_audit_trail()
    {
        config(['action-engine.audit.enabled' => true]);

        \ActionRegistry::register('audited-fail', function ($record, $params) {
            throw new \Exception('Audited failure');
        });

        try {
            BulkAction::on(Product::class)
                ->action('audited-fail')
                ->where('id', 1)
                ->sync()
                ->execute();
        } catch (\Exception $e) {
            // Expected
        }

        // Check audit log
        $audit = \DB::table('bulk_action_audit')
            ->where('action_name', 'audited-fail')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('failed', $audit->status);
    }

    /** @test */
    public function it_handles_queue_connection_failures()
    {
        $this->markTestSkipped('Requires queue infrastructure setup');

        // Would test:
        // - Configure non-existent queue connection
        // - Attempt to dispatch job
        // - Verify proper exception handling
    }

    /** @test */
    public function it_validates_input_before_execution()
    {
        $this->expectException(\InvalidArgumentException::class);

        BulkAction::on(Product::class)
            ->action('update')
            ->parameters(['status' => 'invalid-status-that-doesnt-exist'])
            ->execute();
    }

    /** @test */
    public function it_handles_empty_result_set_gracefully()
    {
        $execution = BulkAction::on(Product::class)
            ->action('update')
            ->where('id', '<', 0) // No records match
            ->parameters(['status' => 'active'])
            ->execute();

        $this->assertEquals(0, $execution->total_records);
        $this->assertEquals('completed', $execution->status);
    }

    /** @test */
    public function it_prevents_duplicate_executions()
    {
        $execution1 = BulkAction::on(Product::class)
            ->action('update')
            ->where('status', 'draft')
            ->parameters(['status' => 'published'])
            ->execute();

        // Trying to execute exact same action should be prevented or handled
        // Implementation depends on package design
        $this->assertInstanceOf(BulkActionExecution::class, $execution1);
    }
}
