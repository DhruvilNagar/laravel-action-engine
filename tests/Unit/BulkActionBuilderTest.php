<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Actions\BulkActionBuilder;
use DhruvilNagar\ActionEngine\Actions\ActionExecutor;
use DhruvilNagar\ActionEngine\Tests\Fixtures\TestModel;
use DhruvilNagar\ActionEngine\Tests\TestCase;
use Mockery;

class BulkActionBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test records
        TestModel::create(['name' => 'Test 1', 'email' => 'test1@example.com', 'status' => 'active']);
        TestModel::create(['name' => 'Test 2', 'email' => 'test2@example.com', 'status' => 'inactive']);
        TestModel::create(['name' => 'Test 3', 'email' => 'test3@example.com', 'status' => 'active']);
    }

    /** @test */
    public function it_can_set_model_class(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $result = $builder->on(TestModel::class);

        $this->assertSame($builder, $result);
        $this->assertEquals(TestModel::class, $builder->getModelClass());
    }

    /** @test */
    public function it_can_set_action_name(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $result = $builder->action('delete');

        $this->assertSame($builder, $result);
        $this->assertEquals('delete', $builder->getActionName());
    }

    /** @test */
    public function it_can_add_where_conditions(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)
            ->where('status', 'active')
            ->where('name', 'like', '%Test%');

        $conditions = $builder->getWhereConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals('active', $conditions[0]['value']);
    }

    /** @test */
    public function it_can_add_where_in_conditions(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)
            ->whereIn('id', [1, 2, 3]);

        $conditions = $builder->getWhereConditions();

        $this->assertCount(1, $conditions);
        $this->assertEquals('whereIn', $conditions[0]['type']);
        $this->assertEquals([1, 2, 3], $conditions[0]['values']);
    }

    /** @test */
    public function it_can_set_specific_ids(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)->ids([1, 2, 3]);

        $this->assertEquals([1, 2, 3], $builder->getTargetIds());
    }

    /** @test */
    public function it_can_set_parameters(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->with(['reason' => 'Test reason', 'force' => true]);

        $params = $builder->getParameters();

        $this->assertEquals('Test reason', $params['reason']);
        $this->assertTrue($params['force']);
    }

    /** @test */
    public function it_can_set_batch_size(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->batchSize(100);

        $this->assertEquals(100, $builder->getBatchSize());
    }

    /** @test */
    public function it_can_enable_sync_mode(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->sync();

        $this->assertFalse($builder->shouldQueue());
    }

    /** @test */
    public function it_can_enable_undo(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->withUndo(14);

        $this->assertTrue($builder->hasUndo());
        $this->assertEquals(14, $builder->getUndoExpiryDays());
    }

    /** @test */
    public function it_can_build_query(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)
            ->where('status', 'active');

        $query = $builder->buildQuery();

        $this->assertEquals(2, $query->count());
    }

    /** @test */
    public function it_can_count_affected_records(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)
            ->where('status', 'active');

        $this->assertEquals(2, $builder->count());
    }

    /** @test */
    public function it_can_preview_affected_records(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class);

        $preview = $builder->preview(2);

        $this->assertCount(2, $preview);
    }

    /** @test */
    public function it_returns_serializable_filters(): void
    {
        $executor = Mockery::mock(ActionExecutor::class);
        $builder = new BulkActionBuilder($executor);

        $builder->on(TestModel::class)
            ->ids([1, 2])
            ->where('status', 'active');

        $filters = $builder->getSerializableFilters();

        $this->assertArrayHasKey('ids', $filters);
        $this->assertArrayHasKey('where', $filters);
        $this->assertEquals([1, 2], $filters['ids']);
    }
}
