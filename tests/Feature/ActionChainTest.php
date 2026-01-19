<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Facades\ActionRegistry;
use DhruvilNagar\ActionEngine\Facades\BulkAction;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;

class ActionChainTest extends TestCase
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
    public function it_can_chain_multiple_actions(): void
    {
        $ids = TestModel::limit(5)->pluck('id')->toArray();

        // First action: Update status
        $execution1 = BulkAction::on(TestModel::class)
            ->action('update')
            ->ids($ids)
            ->with(['data' => ['status' => 'archived']])
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution1->status);

        // Verify first action completed
        foreach ($ids as $id) {
            $model = TestModel::find($id);
            $this->assertEquals('archived', $model->status);
        }

        // Second action: Archive the records
        $execution2 = BulkAction::on(TestModel::class)
            ->action('archive')
            ->ids($ids)
            ->with(['reason' => 'Chained action'])
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution2->status);

        // Verify second action completed
        foreach ($ids as $id) {
            $model = TestModel::find($id);
            $this->assertNotNull($model->archived_at);
            $this->assertEquals('Chained action', $model->archive_reason);
        }
    }

    /** @test */
    public function it_can_register_custom_actions(): void
    {
        // Register a custom action
        ActionRegistry::register('custom_action', function ($record, $params) {
            $record->update(['name' => 'Custom: ' . $record->name]);
            return true;
        }, [
            'label' => 'Custom Action',
            'supports_undo' => false,
        ]);

        $this->assertTrue(ActionRegistry::has('custom_action'));

        $ids = TestModel::limit(3)->pluck('id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('custom_action')
            ->ids($ids)
            ->sync()
            ->execute();

        $this->assertEquals('completed', $execution->status);

        // Verify custom action was executed
        foreach ($ids as $id) {
            $model = TestModel::find($id);
            $this->assertStringStartsWith('Custom: ', $model->name);
        }
    }

    /** @test */
    public function it_handles_dry_run_mode(): void
    {
        $ids = TestModel::limit(5)->pluck('id')->toArray();
        $originalNames = TestModel::whereIn('id', $ids)->pluck('name', 'id')->toArray();

        $execution = BulkAction::on(TestModel::class)
            ->action('update')
            ->ids($ids)
            ->with(['data' => ['name' => 'Updated Name']])
            ->dryRun()
            ->sync()
            ->execute();

        $this->assertTrue($execution->is_dry_run);
        $this->assertEquals('completed', $execution->status);
        $this->assertNotNull($execution->dry_run_results);

        // Verify records were NOT actually updated
        foreach ($ids as $id) {
            $model = TestModel::find($id);
            $this->assertEquals($originalNames[$id], $model->name);
        }

        // Verify dry run results contain expected data
        $dryRunCount = is_array($execution->dry_run_results) 
            ? ($execution->dry_run_results['affected_count'] ?? $execution->dry_run_results['count'] ?? 0)
            : 0;
        $this->assertEquals(count($ids), $dryRunCount);
    }

    /** @test */
    public function it_supports_preview_before_execution(): void
    {
        $count = BulkAction::on(TestModel::class)
            ->action('delete')
            ->where('status', 'active')
            ->count();

        $this->assertEquals(10, $count);

        $preview = BulkAction::on(TestModel::class)
            ->action('delete')
            ->where('status', 'active')
            ->preview(3);

        $this->assertCount(3, $preview);
        $this->assertEquals(10, TestModel::count()); // No records deleted
    }
}
