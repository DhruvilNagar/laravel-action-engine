<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use DhruvilNagar\ActionEngine\Support\SchedulerService;
use Illuminate\Console\Command;

class ProcessScheduledCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'action-engine:process-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled bulk actions that are due';

    /**
     * Execute the console command.
     */
    public function handle(SchedulerService $scheduler): int
    {
        $this->info('Processing scheduled bulk actions...');

        $processedCount = $scheduler->processDue();

        if ($processedCount > 0) {
            $this->info("Processed {$processedCount} scheduled action(s).");
        } else {
            $this->info('No scheduled actions are due.');
        }

        return Command::SUCCESS;
    }
}
