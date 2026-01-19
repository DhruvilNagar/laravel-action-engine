<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use DhruvilNagar\ActionEngine\Jobs\CleanupExpiredData;
use Illuminate\Console\Command;

/**
 * CleanupCommand
 * 
 * Artisan command for cleaning up expired and old data from the action engine.
 * 
 * This command removes:
 * - Expired undo records that can no longer be restored
 * - Completed executions older than retention period
 * - Old audit log entries
 * - Orphaned progress records
 * 
 * Can run synchronously (--sync) or dispatch to queue for better performance.
 */
class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     * 
     * @var string
     */
    protected $signature = 'action-engine:cleanup 
                            {--sync : Run cleanup synchronously instead of dispatching a job}';

    /**
     * The console command description.
     * 
     * @var string
     */
    protected $description = 'Clean up expired undo data, old execution records, and audit logs';

    /**
     * Execute the console command.
     * 
     * @return int Command exit code
     */
    public function handle(): int
    {
        $this->info('Starting Action Engine cleanup...');

        if ($this->option('sync')) {
            $job = new CleanupExpiredData();
            $job->handle(
                app(\DhruvilNagar\ActionEngine\Support\UndoManager::class),
                app(\DhruvilNagar\ActionEngine\Support\AuditLogger::class)
            );
            $this->info('Cleanup completed synchronously.');
        } else {
            dispatch(new CleanupExpiredData());
            $this->info('Cleanup job dispatched to queue.');
        }

        return Command::SUCCESS;
    }
}
