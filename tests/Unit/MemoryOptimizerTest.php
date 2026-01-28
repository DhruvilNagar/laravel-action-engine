<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Support\MemoryOptimizer;
use DhruvilNagar\ActionEngine\Tests\TestCase;

/**
 * MemoryOptimizerTest
 * 
 * Tests the memory optimizer functionality.
 */
class MemoryOptimizerTest extends TestCase
{
    protected MemoryOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new MemoryOptimizer();
    }

    /** @test */
    public function it_can_get_current_memory_usage()
    {
        $usage = $this->optimizer->getCurrentMemoryUsage();

        $this->assertIsFloat($usage);
        $this->assertGreaterThanOrEqual(0, $usage);
        $this->assertLessThanOrEqual(1, $usage);
    }

    /** @test */
    public function it_can_get_memory_limit()
    {
        $limit = $this->optimizer->getMemoryLimit();

        $this->assertIsInt($limit);
        $this->assertTrue($limit > 0 || $limit === -1); // -1 means unlimited
    }

    /** @test */
    public function it_formats_memory_correctly()
    {
        $formatted = $this->optimizer->getCurrentMemoryFormatted();

        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s+(B|KB|MB|GB)/', $formatted);
    }

    /** @test */
    public function it_detects_when_approaching_memory_limit()
    {
        $optimizer = new MemoryOptimizer(0.01); // 1% threshold for testing

        $isApproaching = $optimizer->isApproachingLimit();

        $this->assertIsBool($isApproaching);
    }

    /** @test */
    public function it_recommends_batch_size_based_on_memory()
    {
        $currentBatchSize = 100;

        $recommended = $this->optimizer->getRecommendedBatchSize($currentBatchSize);

        $this->assertIsInt($recommended);
        $this->assertGreaterThan(0, $recommended);
    }

    /** @test */
    public function it_increases_batch_size_when_memory_is_low()
    {
        $optimizer = new MemoryOptimizer(0.9); // High threshold

        $recommended = $optimizer->getRecommendedBatchSize(100);

        // Should increase when usage is low
        $this->assertGreaterThanOrEqual(100, $recommended);
    }

    /** @test */
    public function it_decreases_batch_size_when_memory_is_high()
    {
        // Simulate high memory usage by allocating memory
        $memoryHog = str_repeat('a', 1024 * 1024 * 10); // 10MB

        $optimizer = new MemoryOptimizer(0.5); // Lower threshold

        $recommended = $optimizer->getRecommendedBatchSize(1000);

        // Should reduce batch size or keep it same
        $this->assertLessThanOrEqual(1000, $recommended);

        unset($memoryHog);
    }

    /** @test */
    public function it_respects_min_and_max_batch_size()
    {
        $optimizer = new MemoryOptimizer(0.8, 50, 500);

        $recommended = $optimizer->getRecommendedBatchSize(10);

        $this->assertGreaterThanOrEqual(50, $recommended);
        $this->assertLessThanOrEqual(500, $recommended);
    }

    /** @test */
    public function it_calculates_optimal_batch_size()
    {
        $estimatedRecordSize = 1024; // 1KB per record

        $optimal = $this->optimizer->calculateOptimalBatchSize($estimatedRecordSize);

        $this->assertIsInt($optimal);
        $this->assertGreaterThan(0, $optimal);
    }

    /** @test */
    public function it_can_force_garbage_collection()
    {
        $memoryBefore = memory_get_usage(true);

        $this->optimizer->forceGarbageCollection();

        $memoryAfter = memory_get_usage(true);

        // Just ensure it doesn't crash
        $this->assertIsInt($memoryBefore);
        $this->assertIsInt($memoryAfter);
    }

    /** @test */
    public function it_records_memory_samples()
    {
        $this->optimizer->recordMemorySample();
        $this->optimizer->recordMemorySample();
        $this->optimizer->recordMemorySample();

        $average = $this->optimizer->getAverageMemoryUsage();

        $this->assertIsFloat($average);
        $this->assertGreaterThan(0, $average);
    }

    /** @test */
    public function it_limits_number_of_samples()
    {
        // Record more than max samples (10)
        for ($i = 0; $i < 20; $i++) {
            $this->optimizer->recordMemorySample();
        }

        $stats = $this->optimizer->getStatistics();

        $this->assertLessThanOrEqual(10, $stats['samples_count']);
    }

    /** @test */
    public function it_estimates_memory_per_record()
    {
        $this->optimizer->recordMemorySample();

        // Simulate processing
        $data = str_repeat('x', 1024 * 100); // 100KB

        $this->optimizer->recordMemorySample();

        $estimate = $this->optimizer->estimateMemoryPerRecord(10);

        $this->assertIsInt($estimate);
        $this->assertGreaterThan(0, $estimate);

        unset($data);
    }

    /** @test */
    public function it_provides_memory_statistics()
    {
        $stats = $this->optimizer->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current_usage', $stats);
        $this->assertArrayHasKey('peak_usage', $stats);
        $this->assertArrayHasKey('memory_limit', $stats);
        $this->assertArrayHasKey('usage_percentage', $stats);
        $this->assertArrayHasKey('is_approaching_limit', $stats);
    }

    /** @test */
    public function it_detects_when_should_pause_processing()
    {
        $optimizer = new MemoryOptimizer(0.8);

        $shouldPause = $optimizer->shouldPauseProcessing();

        $this->assertIsBool($shouldPause);
    }

    /** @test */
    public function it_can_reset_tracking()
    {
        $this->optimizer->recordMemorySample();
        $this->optimizer->recordMemorySample();

        $this->optimizer->reset();

        $stats = $this->optimizer->getStatistics();

        $this->assertEquals(0, $stats['samples_count']);
    }

    /** @test */
    public function it_handles_unlimited_memory()
    {
        // Cannot easily test unlimited memory, but ensure it doesn't crash
        $usage = $this->optimizer->getCurrentMemoryUsage();

        if ($this->optimizer->getMemoryLimit() === -1) {
            $this->assertEquals(0, $usage);
        } else {
            $this->assertGreaterThan(0, $usage);
        }
    }

    /** @test */
    public function it_optimizes_before_batch()
    {
        $memoryBefore = memory_get_usage(true);

        $this->optimizer->optimizeBeforeBatch();

        // Just ensure it completes without error
        $this->assertTrue(true);
    }

    /** @test */
    public function it_clears_query_log()
    {
        \DB::enableQueryLog();
        \DB::select('SELECT 1');

        $this->optimizer->clearQueryLog();

        $queryLog = \DB::getQueryLog();

        $this->assertEmpty($queryLog);

        \DB::disableQueryLog();
    }
}
