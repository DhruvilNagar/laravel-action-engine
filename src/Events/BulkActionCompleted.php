<?php

namespace DhruvilNagar\ActionEngine\Events;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkActionCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BulkActionExecution $execution
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channelPrefix = config('action-engine.broadcasting.channel_prefix', 'bulk-action');

        return [
            new PrivateChannel("{$channelPrefix}.{$this->execution->uuid}"),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->execution->uuid,
            'status' => $this->execution->status,
            'total_records' => $this->execution->total_records,
            'processed_records' => $this->execution->processed_records,
            'failed_records' => $this->execution->failed_records,
            'can_undo' => $this->execution->can_undo,
            'undo_expires_at' => $this->execution->undo_expires_at?->toIso8601String(),
            'completed_at' => $this->execution->completed_at?->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'completed';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        return config('action-engine.broadcasting.enabled', false);
    }
}
