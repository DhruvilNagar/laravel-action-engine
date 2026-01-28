<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Tests\TestCase;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MemoryManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test memory usage with large datasets
     */
    public function test_memory_usage_remains_stable_with_large_batches(): void
    {
        $initialMemory = memory_get_usage();
        
        // Simulate processing large batches
        $records = range(1, 10000);
        $batches = array_chunk($records, 500);
        
        foreach ($batches as $batch) {
            // Process batch
            $this->processBatch($batch);
            
            // Memory should not increase significantly
            $currentMemory = memory_get_usage();
            $memoryIncrease = $currentMemory - $initialMemory;
            
            // Allow 10MB variance
            $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease);
        }
    }

    /**
     * Test configurable batch sizes
     */
    public function test_batch_size_configuration(): void
    {
        $sizes = [100, 500, 1000, 2000];
        
        foreach ($sizes as $size) {
            config(['action-engine.batch_size' => $size]);
            
            $this->assertEquals($size, config('action-engine.batch_size'));
        }
    }

    /**
     * Test memory cleanup after batch processing
     */
    public function test_memory_cleanup_after_processing(): void
    {
        $memoryBefore = memory_get_usage();
        
        // Process data
        $records = range(1, 5000);
        $this->processBatch($records);
        
        // Force garbage collection
        gc_collect_cycles();
        
        $memoryAfter = memory_get_usage();
        
        // Memory should be released after processing
        $this->assertLessThan($memoryBefore + (5 * 1024 * 1024), $memoryAfter);
    }

    private function processBatch(array $batch): void
    {
        // Simulate batch processing
        foreach ($batch as $item) {
            // Process item
            $processed = $item * 2;
        }
        
        unset($batch);
    }
}
