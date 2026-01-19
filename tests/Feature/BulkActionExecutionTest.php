<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;

class BulkActionExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test records
        for ($i = 1; $i <= 10; $i++) {
            TestModel::create([
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'status' => $i % 2 === 0 ? 'active' : 'inactive',
            ]);
        }
    }

    /** @test */
    public function it_can_execute_bulk_delete_synchronously(): void
    {
        $ids = TestModel::where('status', 'inactive')->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->sync()
            ->execute();

        $this->assertInstanceOf(BulkActionExecution::class, $execution);
        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(5, $execution->processed_records);
        $this->assertEquals(0, $execution->failed_records);

        // Verify records are deleted (soft delete)
        $this->assertEquals(5, TestModel::count());
        $this->assertEquals(5, TestModel::onlyTrashed()->count());
    }

    /** @test */
    public function it_can_execute_bulk_update_synchronously(): void
    {
        $ids = TestModel::where('status', 'active')->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->ids($ids)
            ->with(['data' => ['status' => 'pending']])
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(5, $execution->processed_records);

        // Verify records are updated
        $this->assertEquals(5, TestModel::where('status', 'pending')->count());
        $this->assertEquals(0, TestModel::where('status', 'active')->count());
    }

    /** @test */
    public function it_can_execute_bulk_archive(): void
    {
        $ids = TestModel::take(3)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('archive')
            ->ids($ids)
            ->with(['reason' => 'Testing archive'])
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(3, $execution->processed_records);

        // Verify records are archived
        $archived = TestModel::whereNotNull('archived_at')->get();
        $this->assertCount(3, $archived);
        $this->assertEquals('Testing archive', $archived->first()->archive_reason);
    }

    /** @test */
    public function it_can_execute_with_where_conditions(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->where('status', 'active')
            ->with(['data' => ['status' => 'verified']])
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(5, $execution->processed_records);
        $this->assertEquals(5, TestModel::where('status', 'verified')->count());
    }

    /** @test */
    public function it_creates_undo_records_when_enabled(): void
    {
        $ids = TestModel::take(3)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->withUndo(7)
            ->sync()
            ->execute();

        $this->assertTrue($execution->can_undo);
        $this->assertNotNull($execution->undo_expires_at);
        $this->assertCount(3, $execution->undoRecords);
    }

    /** @test */
    public function it_can_run_dry_run(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->where('status', 'inactive')
            ->dryRun()
            ->execute();

        $this->assertTrue($execution->is_dry_run);
        $this->assertEquals(5, $execution->total_records);
        $this->assertNotNull($execution->dry_run_results);

        // Verify no records were actually deleted
        $this->assertEquals(10, TestModel::count());
    }

    /** @test */
    public function it_tracks_progress_correctly(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->where('status', 'active')
            ->with(['data' => ['status' => 'processed']])
            ->batchSize(2)
            ->sync()
            ->execute();

        $this->assertEquals(5, $execution->total_records);
        $this->assertEquals(5, $execution->processed_records);
        $this->assertEquals(100, $execution->progress_percentage);
    }

    /** @test */
    public function it_can_restore_soft_deleted_records(): void
    {
        // First, delete some records
        $ids = TestModel::take(3)->pluck('id')->toArray();
        TestModel::whereIn('id', $ids)->delete();

        $this->assertEquals(7, TestModel::count());
        $this->assertEquals(3, TestModel::onlyTrashed()->count());

        // Now restore them
        $execution = BulkAction::on(TestModel::class)
            ->action('restore')
            ->ids($ids)
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->status);
        $this->assertEquals(3, $execution->processed_records);
        $this->assertEquals(10, TestModel::count());
    }
}
