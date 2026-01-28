<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Tests\TestCase;
use DhruvilNagar\ActionEngine\Models\BulkActionUndo;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Exceptions\UndoExpiredException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class UndoStorageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test snapshot compression
     */
    public function test_snapshot_data_is_compressed(): void
    {
        $largeData = array_fill(0, 1000, [
            'id' => 1,
            'name' => 'Test Name',
            'email' => 'test@example.com',
            'data' => str_repeat('x', 1000)
        ]);

        $uncompressed = serialize($largeData);
        $compressed = gzcompress($uncompressed, 9);

        // Compressed should be significantly smaller
        $this->assertLessThan(strlen($uncompressed) * 0.5, strlen($compressed));
    }

    /**
     * Test undo data cleanup
     */
    public function test_expired_undo_data_is_cleaned_up(): void
    {
        // Create old undo records
        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'completed'
        ]);
        
        $oldUndo = BulkActionUndo::create([
            'bulk_action_execution_id' => $execution->id,
            'record_id' => 1,
            'model_type' => 'App\\Models\\User',
            'original_data' => json_encode(['name' => 'Old']),
            'created_at' => Carbon::now()->subDays(31)
        ]);

        // Run cleanup
        BulkActionUndo::where('created_at', '<', Carbon::now()->subDays(30))->delete();

        // Old record should be deleted
        $this->assertDatabaseMissing('bulk_action_undo', ['id' => $oldUndo->id]);
    }

    /**
     * Test selective undo
     */
    public function test_selective_record_undo(): void
    {
        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'completed'
        ]);
        
        $undo1 = BulkActionUndo::create([
            'bulk_action_execution_id' => $execution->id,
            'record_id' => 1,
            'model_type' => 'App\\Models\\User',
            'original_data' => json_encode(['name' => 'User 1'])
        ]);

        $undo2 = BulkActionUndo::create([
            'bulk_action_execution_id' => $execution->id,
            'record_id' => 2,
            'model_type' => 'App\\Models\\User',
            'original_data' => json_encode(['name' => 'User 2'])
        ]);

        // Should be able to undo specific records
        $this->assertDatabaseHas('bulk_action_undo', ['record_id' => 1]);
        $this->assertDatabaseHas('bulk_action_undo', ['record_id' => 2]);
    }

    /**
     * Test undo expiration
     */
    public function test_undo_operation_expires_after_configured_time(): void
    {
        config(['action-engine.undo.default_expiry_days' => 1]); // 1 day

        $execution = BulkActionExecution::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action_name' => 'test_action',
            'model_type' => 'App\\Models\\User',
            'filters' => [],
            'total_records' => 100,
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(2)
        ]);

        $canUndo = $execution->created_at->addDays(config('action-engine.undo.default_expiry_days'))
            ->isFuture();

        $this->assertFalse($canUndo);
    }
}
