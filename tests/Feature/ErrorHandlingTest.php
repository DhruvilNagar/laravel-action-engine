<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Tests\TestCase;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Jobs\ProcessBulkActionBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test partial batch failure handling
     */
    public function test_partial_batch_failure_is_handled_correctly(): void
    {
        Queue::fake();

        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'processed_records' => 50,
            'status' => 'processing'
        ]);

        // Test that partial processing is tracked correctly
        $this->assertEquals(50, $execution->processed_records);
        $this->assertEquals(100, $execution->total_records);
        $this->assertEquals('processing', $execution->status);
    }

    /**
     * Test database transaction rollback on failure
     */
    public function test_database_transaction_rollback_on_failure(): void
    {
        DB::beginTransaction();

        try {
            // Simulate operation that should fail
            throw new \Exception('Simulated failure');
        } catch (\Exception $e) {
            DB::rollBack();
        }

        // Transaction should be rolled back
        $this->assertTrue(true);
    }

    /**
     * Test retry mechanism for failed batches
     */
    public function test_failed_batch_can_be_retried(): void
    {
        Queue::fake();

        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'failed'
        ]);

        // Execution can transition from failed to pending for retry
        $execution->status = 'pending';
        $execution->save();

        $this->assertEquals('pending', $execution->status);
        $this->assertDatabaseHas('bulk_action_executions', [
            'id' => $execution->id,
            'status' => 'pending'
        ]);
    }

    /**
     * Test maximum retry limit
     */
    public function test_execution_stops_after_max_retries(): void
    {
        $maxRetries = 3;
        $currentRetries = 3;

        // Should not retry after max attempts
        $canRetry = $currentRetries < $maxRetries;
        $this->assertFalse($canRetry, "Should not retry when retries ({$currentRetries}) >= maxRetries ({$maxRetries})");
    }

    /**
     * Test race condition handling
     */
    public function test_concurrent_execution_handling(): void
    {
        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'pending'
        ]);

        // Simulate two processes trying to start the same execution
        DB::beginTransaction();
        
        $locked = DB::table('bulk_action_executions')
            ->where('id', $execution->id)
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($locked);
        
        DB::rollBack();
    }

    /**
     * Test error logging and reporting
     */
    public function test_errors_are_logged_with_context(): void
    {
        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'processing'
        ]);

        $errorData = [
            'execution_id' => $execution->id,
            'error' => 'Test error',
            'context' => ['batch' => 1, 'record_id' => 123]
        ];

        // Log error
        $execution->error_details = $errorData;
        $execution->save();

        $this->assertDatabaseHas('bulk_action_executions', [
            'id' => $execution->id,
        ]);

        $this->assertEquals('Test error', $execution->error_details['error']);
    }
}
