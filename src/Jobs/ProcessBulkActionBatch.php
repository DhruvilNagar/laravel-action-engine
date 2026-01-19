<?php

namespace DhruvilNagar\ActionEngine\Jobs;

use DhruvilNagar\ActionEngine\Actions\ActionExecutor;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBulkActionBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $executionUuid,
        public int $batchNumber,
        public array $recordIds,
        public array $parameters,
        public bool $captureUndo
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ActionExecutor $executor): void
    {
        // Check if batch was cancelled
        if ($this->batch()?->cancelled()) {
            return;
        }

        $executor->processBatch(
            $this->executionUuid,
            $this->batchNumber,
            $this->recordIds,
            $this->parameters,
            $this->captureUndo
        );
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'bulk-action',
            "execution:{$this->executionUuid}",
            "batch:{$this->batchNumber}",
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function retryAfter(): int
    {
        return $this->backoff * $this->attempts();
    }
}
