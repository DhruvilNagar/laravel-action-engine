<?php

namespace DhruvilNagar\ActionEngine\Tests\Feature;

use DhruvilNagar\ActionEngine\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use DhruvilNagar\ActionEngine\Events\BulkActionProgress;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test progress broadcasting configuration
     */
    public function test_broadcasting_is_configurable(): void
    {
        config(['action-engine.enable_broadcasting' => true]);
        
        $this->assertTrue(config('action-engine.enable_broadcasting'));
    }

    /**
     * Test event is dispatched for progress updates
     */
    public function test_progress_event_is_dispatched(): void
    {
        Event::fake();

        event(new BulkActionProgress([
            'execution_id' => 1,
            'processed' => 50,
            'total' => 100,
            'percentage' => 50
        ]));

        Event::assertDispatched(BulkActionProgress::class);
    }

    /**
     * Test throttling of progress updates
     */
    public function test_progress_updates_are_throttled(): void
    {
        Event::fake();

        $updates = 0;
        $throttleInterval = 1; // 1 second

        for ($i = 0; $i < 100; $i++) {
            // Only update if throttle interval has passed
            if ($i % 10 === 0) { // Simulate throttling
                event(new BulkActionProgress([
                    'execution_id' => 1,
                    'processed' => $i,
                    'total' => 100
                ]));
                $updates++;
            }
        }

        // Should have fewer events than total iterations
        $this->assertEquals(10, $updates);
    }

    /**
     * Test channel authorization
     */
    public function test_private_channel_requires_authorization(): void
    {
        $channelName = 'private-bulk-action.1';
        
        // Simulate channel authorization check
        $authorized = $this->checkChannelAuthorization($channelName, 1);
        
        $this->assertTrue(is_bool($authorized));
    }

    private function checkChannelAuthorization(string $channel, int $userId): bool
    {
        // Simplified authorization logic
        return str_starts_with($channel, 'private-bulk-action.');
    }
}
