<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ProgressTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test records
        for ($i = 1; $i <= 30; $i++) {
            TestModel::create([
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'status' => 'active',
            ]);
        }
    }

    /** @test */
    public function it_tracks_progress_during_execution(): void
    {
        $ids = TestModel::pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->batchSize(10)
            ->sync()
            ->execute();

        $this->assertInstanceOf(BulkActionExecution::class, $execution);
        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(30, $execution->total_records);
        $this->assertEquals(30, $execution->processed_records);
        $this->assertEquals(100, $execution->progress_percentage);
    }

    /** @test */
    public function it_creates_progress_records_for_each_batch(): void
    {
        $ids = TestModel::pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->batchSize(10)
            ->sync()
            ->execute();

        // Should create 3 batches (30 records / 10 per batch)
        $this->assertEquals(3, $execution->progress()->count());

        foreach ($execution->progress as $progress) {
            $this->assertEquals('completed', $progress->status);
            $this->assertEquals(10, $progress->processed_count);
            $this->assertNotEmpty($progress->affected_ids);
        }
    }

    /** @test */
    public function it_calculates_progress_percentage_correctly(): void
    {
        $ids = TestModel::limit(25)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->batchSize(10)
            ->sync()
            ->execute();

        $this->assertEquals(25, $execution->total_records);
        $this->assertEquals(25, $execution->processed_records);
        $this->assertEquals(100.0, $execution->progress_percentage);
    }

    /** @test */
    public function it_tracks_failed_records(): void
    {
        // Create a scenario where some records will fail
        // This would require mocking or creating a failing action
        $this->markTestIncomplete('Requires implementation of failure scenario');
    }

    /** @test */
    public function it_updates_timestamps_correctly(): void
    {
        $ids = TestModel::limit(10)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->sync()
            ->execute();

        $this->assertNotNull($execution->started_at);
        $this->assertNotNull($execution->completed_at);
        $this->assertGreaterThanOrEqual(
            $execution->started_at,
            $execution->completed_at
        );
    }

    /** @test */
    public function it_emits_progress_events(): void
    {
        Event::fake([
            \DhruvilNagar\ActionEngine\Events\BulkActionStarted::class,
            \DhruvilNagar\ActionEngine\Events\BulkActionCompleted::class,
        ]);

        $ids = TestModel::limit(5)->pluck('id')->toArray();

        BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->sync()
            ->execute();

        Event::assertDispatched(\DhruvilNagar\ActionEngine\Events\BulkActionStarted::class);
        Event::assertDispatched(\DhruvilNagar\ActionEngine\Events\BulkActionCompleted::class);
    }
}
