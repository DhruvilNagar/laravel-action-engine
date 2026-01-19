<?php

namespace DhruvilNagar\ActionEngine\Events;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkActionUndone
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BulkActionExecution $execution,
        public int $restoredCount
    ) {}
}
