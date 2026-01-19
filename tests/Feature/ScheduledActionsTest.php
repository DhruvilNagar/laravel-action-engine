<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use Carbon\Carbon;
use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;

class ScheduledActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test records
        for ($i = 1; $i <= 10; $i++) {
            TestModel::create([
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'status' => 'active',
            ]);
        }
    }

    /** @test */
    public function it_can_schedule_action_for_future(): void
    {
        $scheduledTime = Carbon::now()->addHours(2);
        $ids = TestModel::limit(5)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->scheduleFor($scheduledTime)
            ->execute();

        $this->assertEquals('scheduled', $execution->status);
        $this->assertNotNull($execution->scheduled_for);
        $this->assertEquals(
            $scheduledTime->format('Y-m-d H:i'),
            $execution->scheduled_for->format('Y-m-d H:i')
        );

        // Records should not be deleted yet
        $this->assertEquals(10, TestModel::count());
    }

    /** @test */
    public function it_respects_scheduled_timezone(): void
    {
        $scheduledTime = Carbon::now()->addHours(1);
        $ids = TestModel::limit(3)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->scheduleFor($scheduledTime, 'America/New_York')
            ->execute();

        $this->assertEquals('scheduled', $execution->status);
        $this->assertNotNull($execution->scheduled_timezone);
        $this->assertEquals('America/New_York', $execution->scheduled_timezone);
    }

    /** @test */
    public function it_prevents_immediate_execution_for_scheduled_actions(): void
    {
        $scheduledTime = Carbon::now()->addMinutes(30);
        $ids = TestModel::limit(5)->pluck('id')->toArray();
        $originalCount = TestModel::count();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->scheduleFor($scheduledTime)
            ->execute();

        // Verify no records were deleted immediately
        $this->assertEquals($originalCount, TestModel::count());
        $this->assertEquals('scheduled', $execution->status);
        $this->assertEquals(0, $execution->processed_records);
    }

    /** @test */
    public function it_can_cancel_scheduled_action(): void
    {
        $scheduledTime = Carbon::now()->addHours(1);
        $ids = TestModel::limit(5)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->scheduleFor($scheduledTime)
            ->execute();

        $this->assertEquals('scheduled', $execution->status);

        // Cancel the scheduled action
        $execution->cancel();

        $this->assertEquals('cancelled', $execution->status);
    }

    /** @test */
    public function it_stores_multiple_scheduled_actions(): void
    {
        $scheduledTime1 = Carbon::now()->addHours(1);
        $scheduledTime2 = Carbon::now()->addHours(2);
        $scheduledTime3 = Carbon::now()->addHours(3);

        $execution1 = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids([1, 2])
            ->scheduleFor($scheduledTime1)
            ->execute();

        $execution2 = BulkAction::on(TestModel::class)
            ->action('update')
            ->ids([3, 4])
            ->with(['data' => ['status' => 'inactive']])
            ->scheduleFor($scheduledTime2)
            ->execute();

        $execution3 = BulkAction::on(TestModel::class)
            ->action('archive')
            ->ids([5, 6])
            ->scheduleFor($scheduledTime3)
            ->execute();

        $this->assertEquals('scheduled', $execution1->status);
        $this->assertEquals('scheduled', $execution2->status);
        $this->assertEquals('scheduled', $execution3->status);

        $scheduledCount = BulkActionExecution::where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->count();

        $this->assertEquals(3, $scheduledCount);
    }
}
