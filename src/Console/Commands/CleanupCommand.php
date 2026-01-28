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
                            {--sync : Run cleanup synchronously instead of dispatching a job}
                            {--expired : Only clean up expired undo data}
                            {--old : Only clean up old completed executions}
                            {--failed : Clean up old failed executions}
                            {--audit : Only clean up old audit logs}
                            {--days=30 : Number of days to retain completed data}
                            {--force : Skip confirmation prompts}
                            {--dry-run : Show what would be deleted without actually deleting}';

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
        $this->info('🧹 Starting Action Engine cleanup...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $retentionDays = (int) $this->option('days');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
            $this->newLine();
        }

        // Show what will be cleaned
        $stats = $this->getCleanupStats($retentionDays);
        $this->displayStats($stats);

        // Confirm unless forced or dry run
        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with cleanup?')) {
                $this->info('Cleanup cancelled.');
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $this->info('✓ Dry run completed. No data was deleted.');
            return Command::SUCCESS;
        }

        // Perform cleanup
        $results = $this->performCleanup($retentionDays);
        
        $this->newLine();
        $this->info('✓ Cleanup completed successfully!');
        $this->displayResults($results);

        return Command::SUCCESS;
    }

    /**
     * Get statistics about what will be cleaned.
     */
    protected function getCleanupStats(int $retentionDays): array
    {
        $cutoffDate = now()->subDays($retentionDays);

        $stats = [
            'expired_undo' => 0,
            'old_completed' => 0,
            'old_failed' => 0,
            'old_audit_logs' => 0,
            'orphaned_progress' => 0,
        ];

        if (!$this->option('old') && !$this->option('failed') && !$this->option('audit')) {
            // Count expired undo records
            $stats['expired_undo'] = \DB::table('bulk_action_undo')
                ->where('expires_at', '<', now())
                ->count();
        }

        if (!$this->option('expired') && !$this->option('failed') && !$this->option('audit')) {
            // Count old completed executions
            $stats['old_completed'] = \DB::table('bulk_action_executions')
                ->where('status', 'completed')
                ->where('completed_at', '<', $cutoffDate)
                ->count();
        }

        if ($this->option('failed') || (!$this->option('expired') && !$this->option('old') && !$this->option('audit'))) {
            // Count old failed executions
            $stats['old_failed'] = \DB::table('bulk_action_executions')
                ->where('status', 'failed')
                ->where('created_at', '<', $cutoffDate)
                ->count();
        }

        if ($this->option('audit') || (!$this->option('expired') && !$this->option('old') && !$this->option('failed'))) {
            // Count old audit logs
            $stats['old_audit_logs'] = \DB::table('bulk_action_audit')
                ->where('created_at', '<', $cutoffDate)
                ->count();
        }

        // Count orphaned progress records
        $stats['orphaned_progress'] = \DB::table('bulk_action_progress')
            ->whereNotIn('execution_id', function ($query) {
                $query->select('id')
                    ->from('bulk_action_executions');
            })
            ->count();

        return $stats;
    }

    /**
     * Display cleanup statistics.
     */
    protected function displayStats(array $stats): void
    {
        $this->table(
            ['Category', 'Count'],
            [
                ['Expired undo records', number_format($stats['expired_undo'])],
                ['Old completed executions', number_format($stats['old_completed'])],
                ['Old failed executions', number_format($stats['old_failed'])],
                ['Old audit logs', number_format($stats['old_audit_logs'])],
                ['Orphaned progress records', number_format($stats['orphaned_progress'])],
                ['---', '---'],
                ['Total records to clean', number_format(array_sum($stats))],
            ]
        );

        $this->newLine();
    }

    /**
     * Perform the actual cleanup.
     */
    protected function performCleanup(int $retentionDays): array
    {
        $cutoffDate = now()->subDays($retentionDays);
        $results = [];

        if ($this->option('sync')) {
            $results = $this->cleanupSync($cutoffDate);
        } else {
            dispatch(new CleanupExpiredData($retentionDays));
            $this->info('Cleanup job dispatched to queue.');
            $results['message'] = 'Dispatched to queue';
        }

        return $results;
    }

    /**
     * Perform synchronous cleanup.
     */
    protected function cleanupSync(\DateTimeInterface $cutoffDate): array
    {
        $results = [
            'expired_undo' => 0,
            'old_completed' => 0,
            'old_failed' => 0,
            'old_audit_logs' => 0,
            'orphaned_progress' => 0,
        ];

        $this->withProgressBar(['Cleaning expired undo', 'Cleaning old executions', 'Cleaning audit logs', 'Cleaning orphaned progress'], function ($step) use (&$results, $cutoffDate) {
            switch ($step) {
                case 'Cleaning expired undo':
                    if (!$this->option('old') && !$this->option('failed') && !$this->option('audit')) {
                        $results['expired_undo'] = \DB::table('bulk_action_undo')
                            ->where('expires_at', '<', now())
                            ->delete();
                    }
                    break;

                case 'Cleaning old executions':
                    if (!$this->option('expired') && !$this->option('audit')) {
                        if (!$this->option('failed')) {
                            $results['old_completed'] = \DB::table('bulk_action_executions')
                                ->where('status', 'completed')
                                ->where('completed_at', '<', $cutoffDate)
                                ->delete();
                        }

                        if ($this->option('failed') || true) {
                            $results['old_failed'] = \DB::table('bulk_action_executions')
                                ->where('status', 'failed')
                                ->where('created_at', '<', $cutoffDate)
                                ->delete();
                        }
                    }
                    break;

                case 'Cleaning audit logs':
                    if ($this->option('audit') || (!$this->option('expired') && !$this->option('old') && !$this->option('failed'))) {
                        $results['old_audit_logs'] = \DB::table('bulk_action_audit')
                            ->where('created_at', '<', $cutoffDate)
                            ->delete();
                    }
                    break;

                case 'Cleaning orphaned progress':
                    $results['orphaned_progress'] = \DB::table('bulk_action_progress')
                        ->whereNotIn('execution_id', function ($query) {
                            $query->select('id')
                                ->from('bulk_action_executions');
                        })
                        ->delete();
                    break;
            }
        });

        return $results;
    }

    /**
     * Display cleanup results.
     */
    protected function displayResults(array $results): void
    {
        if (isset($results['message'])) {
            $this->info($results['message']);
            return;
        }

        $this->newLine();
        $this->table(
            ['Category', 'Deleted'],
            [
                ['Expired undo records', number_format($results['expired_undo'])],
                ['Old completed executions', number_format($results['old_completed'])],
                ['Old failed executions', number_format($results['old_failed'])],
                ['Old audit logs', number_format($results['old_audit_logs'])],
                ['Orphaned progress records', number_format($results['orphaned_progress'])],
                ['---', '---'],
                ['Total deleted', number_format(array_sum($results))],
            ]
        );
    }
}
