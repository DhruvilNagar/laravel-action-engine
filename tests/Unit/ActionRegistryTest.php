<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Actions\ActionRegistry;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use DhruvilNagar\ActionEngine\Tests\TestCase;

class ActionRegistryTest extends TestCase
{
    /** @test */
    public function it_can_register_an_action_with_closure(): void
    {
        $registry = new ActionRegistry();

        $registry->register('test-action', function ($record, $params) {
            return true;
        });

        $this->assertTrue($registry->has('test-action'));
    }

    /** @test */
    public function it_can_register_an_action_with_class(): void
    {
        $registry = new ActionRegistry();

        $registry->register('delete', \DhruvilNagar\ActionEngine\Actions\BuiltIn\DeleteAction::class);

        $this->assertTrue($registry->has('delete'));
    }

    /** @test */
    public function it_can_get_registered_action(): void
    {
        $registry = new ActionRegistry();
        $handler = function ($record, $params) {
            return true;
        };

        $registry->register('test-action', $handler);

        $this->assertSame($handler, $registry->get('test-action'));
    }

    /** @test */
    public function it_throws_exception_for_unregistered_action(): void
    {
        $registry = new ActionRegistry();

        $this->expectException(InvalidActionException::class);

        $registry->get('non-existent-action');
    }

    /** @test */
    public function it_can_list_all_registered_actions(): void
    {
        $registry = new ActionRegistry();

        $registry->register('action1', function () {});
        $registry->register('action2', function () {});
        $registry->register('action3', function () {});

        $all = $registry->all();

        $this->assertCount(3, $all);
        $this->assertContains('action1', $all);
        $this->assertContains('action2', $all);
        $this->assertContains('action3', $all);
    }

    /** @test */
    public function it_can_unregister_an_action(): void
    {
        $registry = new ActionRegistry();

        $registry->register('test-action', function () {});
        $this->assertTrue($registry->has('test-action'));

        $registry->unregister('test-action');
        $this->assertFalse($registry->has('test-action'));
    }

    /** @test */
    public function it_stores_action_metadata(): void
    {
        $registry = new ActionRegistry();

        $registry->register('test-action', function () {}, [
            'label' => 'Test Action',
            'supports_undo' => true,
            'description' => 'A test action',
        ]);

        $metadata = $registry->getMetadata('test-action');

        $this->assertEquals('Test Action', $metadata['label']);
        $this->assertTrue($metadata['supports_undo']);
        $this->assertEquals('A test action', $metadata['description']);
    }

    /** @test */
    public function it_returns_undoable_actions(): void
    {
        $registry = new ActionRegistry();

        $registry->register('action1', function () {}, ['supports_undo' => true]);
        $registry->register('action2', function () {}, ['supports_undo' => false]);
        $registry->register('action3', function () {}, ['supports_undo' => true]);

        $undoable = $registry->getUndoableActions();

        $this->assertCount(2, $undoable);
        $this->assertArrayHasKey('action1', $undoable);
        $this->assertArrayHasKey('action3', $undoable);
    }

    /** @test */
    public function it_can_register_multiple_actions_at_once(): void
    {
        $registry = new ActionRegistry();

        $registry->registerMany([
            'action1' => function () {},
            'action2' => [
                'handler' => function () {},
                'label' => 'Action 2',
            ],
        ]);

        $this->assertTrue($registry->has('action1'));
        $this->assertTrue($registry->has('action2'));
        $this->assertEquals('Action 2', $registry->getLabel('action2'));
    }
}
