<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MonitorCommand
 * 
 * Monitor bulk action executions and display real-time statistics.
 * Provides insights into system health, performance, and active operations.
 */
class MonitorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'action-engine:monitor
                            {--watch : Continuously monitor and refresh statistics}
                            {--interval=5 : Refresh interval in seconds for watch mode}
                            {--detailed : Show detailed information for each action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor bulk action executions and display statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('watch')) {
            return $this->watchMode();
        }

        $this->displayStatistics();
        return Command::SUCCESS;
    }

    /**
     * Watch mode - continuously display statistics.
     */
    protected function watchMode(): int
    {
        $interval = max(1, (int) $this->option('interval'));

        $this->info("Monitoring Action Engine (refreshing every {$interval}s)");
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        while (true) {
            // Clear screen
            if (PHP_OS_FAMILY !== 'Windows') {
                system('clear');
            }

            $this->line('Last updated: ' . now()->format('Y-m-d H:i:s'));
            $this->newLine();

            $this->displayStatistics();

            sleep($interval);
        }

        return Command::SUCCESS;
    }

    /**
     * Display comprehensive statistics.
     */
    protected function displayStatistics(): void
    {
        $this->displayOverview();
        $this->newLine();

        if ($this->option('detailed')) {
            $this->displayActiveActions();
            $this->newLine();
            $this->displayRecentFailures();
            $this->newLine();
        }

        $this->displayPerformanceMetrics();
    }

    /**
     * Display overview statistics.
     */
    protected function displayOverview(): void
    {
        $stats = [
            'total' => BulkActionExecution::count(),
            'pending' => BulkActionExecution::where('status', 'pending')->count(),
            'processing' => BulkActionExecution::where('status', 'processing')->count(),
            'completed' => BulkActionExecution::where('status', 'completed')->count(),
            'failed' => BulkActionExecution::where('status', 'failed')->count(),
            'cancelled' => BulkActionExecution::where('status', 'cancelled')->count(),
        ];

        $stats['active'] = $stats['pending'] + $stats['processing'];

        // Today's stats
        $today = now()->startOfDay();
        $todayStats = [
            'completed' => BulkActionExecution::where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count(),
            'failed' => BulkActionExecution::where('status', 'failed')
                ->whereDate('created_at', $today)
                ->count(),
        ];

        $this->line('📊 <options=bold>Overview</>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Executions', number_format($stats['total'])],
                ['Active (Pending + Processing)', $this->colorize($stats['active'], 'info')],
                ['  ├─ Pending', number_format($stats['pending'])],
                ['  └─ Processing', number_format($stats['processing'])],
                ['Completed', $this->colorize($stats['completed'], 'success')],
                ['Failed', $this->colorize($stats['failed'], 'error')],
                ['Cancelled', number_format($stats['cancelled'])],
                ['---', '---'],
                ['Completed Today', number_format($todayStats['completed'])],
                ['Failed Today', number_format($todayStats['failed'])],
            ]
        );
    }

    /**
     * Display active actions.
     */
    protected function displayActiveActions(): void
    {
        $activeActions = BulkActionExecution::whereIn('status', ['pending', 'processing'])
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        if ($activeActions->isEmpty()) {
            $this->line('🟢 <options=bold>Active Actions:</> None');
            return;
        }

        $this->line('⚡ <options=bold>Active Actions</>');

        $rows = $activeActions->map(function ($action) {
            $progress = $action->total_records > 0
                ? round(($action->processed_records / $action->total_records) * 100, 1) . '%'
                : '0%';

            return [
                substr($action->uuid, 0, 8),
                $action->action_name,
                $action->status,
                $progress,
                number_format($action->processed_records) . '/' . number_format($action->total_records),
                $action->user ? $action->user->name : 'System',
                $action->created_at->diffForHumans(),
            ];
        })->toArray();

        $this->table(
            ['UUID', 'Action', 'Status', 'Progress', 'Records', 'User', 'Started'],
            $rows
        );
    }

    /**
     * Display recent failures.
     */
    protected function displayRecentFailures(): void
    {
        $failures = BulkActionExecution::where('status', 'failed')
            ->latest()
            ->limit(5)
            ->get();

        if ($failures->isEmpty()) {
            $this->line('✅ <options=bold>Recent Failures:</> None');
            return;
        }

        $this->line('❌ <options=bold>Recent Failures</>');

        $rows = $failures->map(function ($action) {
            return [
                substr($action->uuid, 0, 8),
                $action->action_name,
                str_limit($action->error_message ?? 'Unknown error', 50),
                $action->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table(
            ['UUID', 'Action', 'Error', 'Failed At'],
            $rows
        );
    }

    /**
     * Display performance metrics.
     */
    protected function displayPerformanceMetrics(): void
    {
        $metrics = DB::table('bulk_action_executions')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw('
                COUNT(*) as count,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration,
                MIN(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as min_duration,
                MAX(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as max_duration,
                SUM(processed_records) as total_records,
                AVG(processed_records) as avg_records
            ')
            ->first();

        $this->line('⚡ <options=bold>Performance Metrics</>');

        if (!$metrics || $metrics->count == 0) {
            $this->line('No completed actions yet.');
            return;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Completed Actions', number_format($metrics->count)],
                ['Total Records Processed', number_format($metrics->total_records)],
                ['Avg Records Per Action', number_format($metrics->avg_records, 0)],
                ['---', '---'],
                ['Avg Duration', $this->formatDuration($metrics->avg_duration)],
                ['Min Duration', $this->formatDuration($metrics->min_duration)],
                ['Max Duration', $this->formatDuration($metrics->max_duration)],
            ]
        );

        // Records per second
        if ($metrics->avg_duration > 0) {
            $recordsPerSecond = $metrics->avg_records / $metrics->avg_duration;
            $this->info("  📈 Average throughput: " . number_format($recordsPerSecond, 2) . " records/second");
        }
    }

    /**
     * Format duration in seconds to human-readable string.
     */
    protected function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return 'N/A';
        }

        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        if ($seconds < 3600) {
            return round($seconds / 60, 2) . 'm';
        }

        return round($seconds / 3600, 2) . 'h';
    }

    /**
     * Colorize output based on value.
     */
    protected function colorize(int $value, string $type): string
    {
        $formatted = number_format($value);

        return match ($type) {
            'success' => "<fg=green>{$formatted}</>",
            'error' => "<fg=red>{$formatted}</>",
            'warning' => "<fg=yellow>{$formatted}</>",
            'info' => "<fg=cyan>{$formatted}</>",
            default => $formatted,
        };
    }
}
