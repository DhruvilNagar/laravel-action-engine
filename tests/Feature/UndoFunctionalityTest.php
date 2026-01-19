<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Support\UndoManager;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;

class UndoFunctionalityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test records
        for ($i = 1; $i <= 5; $i++) {
            TestModel::create([
                'name' => "Test User {$i}",
                'email' => "test{$i}@example.com",
                'status' => 'active',
            ]);
        }
    }

    /** @test */
    public function it_can_undo_delete_action(): void
    {
        $ids = TestModel::pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids($ids)
            ->withUndo(7)
            ->sync()
            ->execute();

        // Records should be soft deleted
        $this->assertEquals(0, TestModel::count());
        $this->assertEquals(5, TestModel::onlyTrashed()->count());

        // Verify undo records were created
        $this->assertCount(5, $execution->undoRecords);

        // Undo the action
        $undoManager = app(UndoManager::class);
        $restoredCount = $undoManager->undo($execution->fresh());

        $this->assertEquals(5, $restoredCount);
        $this->assertEquals(5, TestModel::count());
        $this->assertEquals(0, TestModel::onlyTrashed()->count());
    }

    /** @test */
    public function it_can_undo_update_action(): void
    {
        $originalStatuses = TestModel::pluck('status', 'id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->with(['data' => ['status' => 'changed']])
            ->withUndo(7)
            ->sync()
            ->execute();

        // Verify all records are updated
        $this->assertEquals(5, TestModel::where('status', 'changed')->count());

        // Undo the action
        $undoManager = app(UndoManager::class);
        $undoManager->undo($execution->fresh());

        // Verify records are restored to original values
        foreach ($originalStatuses as $id => $status) {
            $this->assertEquals($status, TestModel::find($id)->status);
        }
    }

    /** @test */
    public function it_cannot_undo_after_expiry(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids([1])
            ->withUndo(1) // 1 day expiry
            ->sync()
            ->execute();

        // Manually expire the undo
        $execution->update(['undo_expires_at' => now()->subHour()]);

        $undoManager = app(UndoManager::class);
        $this->assertFalse($undoManager->canUndo($execution->fresh()));
    }

    /** @test */
    public function it_marks_undo_records_as_undone(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids([1, 2])
            ->withUndo()
            ->sync()
            ->execute();

        $undoManager = app(UndoManager::class);
        $undoManager->undo($execution->fresh());

        // All undo records should be marked as undone
        $execution = $execution->fresh();
        $this->assertFalse($execution->can_undo);
        $this->assertTrue($execution->undoRecords->every(fn ($r) => $r->undone));
    }

    /** @test */
    public function it_captures_original_data_for_undo(): void
    {
        $record = TestModel::first();
        $originalName = $record->name;

        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->ids([$record->id])
            ->with(['data' => ['name' => 'New Name']])
            ->withUndo()
            ->sync()
            ->execute();

        $undoRecord = $execution->undoRecords()->first();

        $this->assertArrayHasKey('name', $undoRecord->original_data);
        $this->assertEquals($originalName, $undoRecord->original_data['name']);
    }

    /** @test */
    public function it_returns_time_remaining_for_undo(): void
    {
        $execution = BulkAction::on(TestModel::class)
            ->action('delete')
            ->ids([1])
            ->withUndo(7)
            ->sync()
            ->execute();

        $undoManager = app(UndoManager::class);
        $timeRemaining = $undoManager->getTimeRemaining($execution);

        $this->assertNotNull($timeRemaining);
        $this->assertStringContainsString('day', $timeRemaining);
    }
}
