<?php

namespace DhruvilNagar\ActionEngine\Http\Controllers;

use DhruvilNagar\ActionEngine\Models\BulkActionExecution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * MonitoringDashboardController
 * 
 * Provides JSON endpoints for monitoring dashboard.
 */
class MonitoringDashboardController
{
    /**
     * Get dashboard overview statistics.
     */
    public function overview(Request $request)
    {
        $timeRange = $request->get('range', '24h');
        $startDate = $this->getStartDate($timeRange);

        return response()->json([
            'stats' => $this->getOverviewStats($startDate),
            'chart_data' => $this->getChartData($startDate),
            'recent_actions' => $this->getRecentActions(10),
        ]);
    }

    /**
     * Get real-time metrics.
     */
    public function metrics()
    {
        return response()->json([
            'active' => [
                'pending' => BulkActionExecution::where('status', 'pending')->count(),
                'processing' => BulkActionExecution::where('status', 'processing')->count(),
            ],
            'today' => [
                'completed' => BulkActionExecution::where('status', 'completed')
                    ->whereDate('completed_at', today())
                    ->count(),
                'failed' => BulkActionExecution::where('status', 'failed')
                    ->whereDate('created_at', today())
                    ->count(),
            ],
            'performance' => $this->getPerformanceMetrics(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get system health status.
     */
    public function health()
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
        ];

        // Check for stuck actions
        $stuckActions = BulkActionExecution::where('status', 'processing')
            ->where('updated_at', '<', now()->subHours(2))
            ->count();

        $health['checks']['stuck_actions'] = [
            'status' => $stuckActions === 0 ? 'pass' : 'warning',
            'count' => $stuckActions,
            'message' => $stuckActions > 0 ? "{$stuckActions} actions appear stuck" : 'No stuck actions',
        ];

        // Check failure rate
        $recentTotal = BulkActionExecution::where('created_at', '>', now()->subHour())->count();
        $recentFailed = BulkActionExecution::where('status', 'failed')
            ->where('created_at', '>', now()->subHour())
            ->count();

        $failureRate = $recentTotal > 0 ? ($recentFailed / $recentTotal) * 100 : 0;

        $health['checks']['failure_rate'] = [
            'status' => $failureRate < 10 ? 'pass' : ($failureRate < 25 ? 'warning' : 'fail'),
            'rate' => round($failureRate, 2),
            'message' => round($failureRate, 1) . '% failure rate in last hour',
        ];

        // Check queue backlog
        $queueBacklog = BulkActionExecution::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->count();

        $health['checks']['queue_backlog'] = [
            'status' => $queueBacklog < 10 ? 'pass' : ($queueBacklog < 50 ? 'warning' : 'fail'),
            'count' => $queueBacklog,
            'message' => $queueBacklog > 0 ? "{$queueBacklog} actions waiting >10min" : 'No backlog',
        ];

        // Overall health
        $failedChecks = collect($health['checks'])->where('status', 'fail')->count();
        $warningChecks = collect($health['checks'])->where('status', 'warning')->count();

        if ($failedChecks > 0) {
            $health['status'] = 'unhealthy';
        } elseif ($warningChecks > 0) {
            $health['status'] = 'degraded';
        }

        return response()->json($health);
    }

    /**
     * Get action type breakdown.
     */
    public function actionBreakdown(Request $request)
    {
        $days = $request->get('days', 7);
        $startDate = now()->subDays($days);

        $breakdown = BulkActionExecution::where('created_at', '>=', $startDate)
            ->select('action_name', 'status', DB::raw('count(*) as count'))
            ->groupBy('action_name', 'status')
            ->get()
            ->groupBy('action_name')
            ->map(function ($actions, $actionName) {
                $total = $actions->sum('count');
                $completed = $actions->where('status', 'completed')->sum('count');
                $failed = $actions->where('status', 'failed')->sum('count');

                return [
                    'action' => $actionName,
                    'total' => $total,
                    'completed' => $completed,
                    'failed' => $failed,
                    'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                ];
            })
            ->values();

        return response()->json($breakdown);
    }

    /**
     * Get user activity statistics.
     */
    public function userActivity(Request $request)
    {
        $days = $request->get('days', 30);
        $limit = $request->get('limit', 10);

        $activity = BulkActionExecution::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as action_count'))
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('action_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => $item->user ? [
                        'id' => $item->user->id,
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                    ] : null,
                    'action_count' => $item->action_count,
                ];
            });

        return response()->json($activity);
    }

    /**
     * Get performance trends.
     */
    public function performanceTrends(Request $request)
    {
        $days = $request->get('days', 7);
        $startDate = now()->subDays($days);

        $trends = DB::table('bulk_action_executions')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $startDate)
            ->selectRaw('
                DATE(completed_at) as date,
                COUNT(*) as completed_count,
                SUM(processed_records) as total_records,
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration,
                AVG(processed_records) as avg_records_per_action
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($trends);
    }

    /**
     * Get overview statistics.
     */
    protected function getOverviewStats(\DateTimeInterface $startDate): array
    {
        return [
            'total' => BulkActionExecution::where('created_at', '>=', $startDate)->count(),
            'active' => BulkActionExecution::whereIn('status', ['pending', 'processing'])->count(),
            'completed' => BulkActionExecution::where('status', 'completed')
                ->where('created_at', '>=', $startDate)
                ->count(),
            'failed' => BulkActionExecution::where('status', 'failed')
                ->where('created_at', '>=', $startDate)
                ->count(),
            'total_records_processed' => BulkActionExecution::where('created_at', '>=', $startDate)
                ->sum('processed_records'),
        ];
    }

    /**
     * Get chart data for visualizations.
     */
    protected function getChartData(\DateTimeInterface $startDate): array
    {
        $data = DB::table('bulk_action_executions')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                status,
                COUNT(*) as count
            ')
            ->groupBy('hour', 'status')
            ->orderBy('hour')
            ->get();

        return [
            'labels' => $data->pluck('hour')->unique()->values()->all(),
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $data->where('status', 'completed')->pluck('count')->all(),
                ],
                [
                    'label' => 'Failed',
                    'data' => $data->where('status', 'failed')->pluck('count')->all(),
                ],
            ],
        ];
    }

    /**
     * Get recent actions.
     */
    protected function getRecentActions(int $limit): array
    {
        return BulkActionExecution::with('user:id,name')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($action) {
                return [
                    'uuid' => $action->uuid,
                    'action_name' => $action->action_name,
                    'status' => $action->status,
                    'progress' => $action->total_records > 0
                        ? round(($action->processed_records / $action->total_records) * 100, 1)
                        : 0,
                    'user' => $action->user ? $action->user->name : null,
                    'created_at' => $action->created_at->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Get performance metrics.
     */
    protected function getPerformanceMetrics(): array
    {
        $metrics = DB::table('bulk_action_executions')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDay())
            ->selectRaw('
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration,
                AVG(processed_records) as avg_records,
                SUM(processed_records) as total_records
            ')
            ->first();

        if (!$metrics) {
            return [
                'avg_duration_seconds' => 0,
                'avg_records_per_action' => 0,
                'records_per_second' => 0,
            ];
        }

        $recordsPerSecond = $metrics->avg_duration > 0
            ? $metrics->avg_records / $metrics->avg_duration
            : 0;

        return [
            'avg_duration_seconds' => round($metrics->avg_duration, 2),
            'avg_records_per_action' => round($metrics->avg_records, 0),
            'records_per_second' => round($recordsPerSecond, 2),
            'total_records_today' => $metrics->total_records,
        ];
    }

    /**
     * Get start date from time range.
     */
    protected function getStartDate(string $range): \DateTimeInterface
    {
        return match ($range) {
            '1h' => now()->subHour(),
            '6h' => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }
}
