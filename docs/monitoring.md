# Monitoring & Observability Integration Guide

## Overview

Laravel Action Engine provides comprehensive monitoring and observability features to track performance, errors, and system health.

## Supported Integrations

- **Laravel Telescope** - Development debugging and monitoring
- **Sentry** - Error tracking and reporting
- **Bugsnag** - Error monitoring
- **Prometheus** - Metrics collection
- **Datadog** - Application performance monitoring
- **New Relic** - Application performance monitoring

## Configuration

### Enable Monitoring

```php
// config/action-engine.php
return [
    'monitoring' => [
        'enabled' => true,
        'driver' => env('ACTION_ENGINE_MONITORING_DRIVER', 'log'),
        
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', false),
        ],
        
        'bugsnag' => [
            'enabled' => env('BUGSNAG_ENABLED', false),
        ],
        
        'datadog' => [
            'enabled' => env('DATADOG_ENABLED', false),
            'host' => env('DATADOG_HOST', '127.0.0.1'),
            'port' => env('DATADOG_PORT', 8125),
        ],
        
        'prometheus' => [
            'enabled' => env('PROMETHEUS_ENABLED', false),
        ],
    ],
];
```

## Laravel Telescope Integration

### Installation

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

### Configuration

Telescope automatically tracks:
- Bulk action executions
- Queue jobs
- Database queries
- Cache operations
- Events

### Custom Watchers

```php
// app/Providers/TelescopeServiceProvider.php
use DhruvilNagar\ActionEngine\Support\TelescopeIntegration;

protected function gate()
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}

protected function register()
{
    Telescope::night();
    
    // Tag bulk actions
    Telescope::tag(function (IncomingEntry $entry) {
        if ($entry->type === 'job') {
            if (str_contains($entry->content['name'], 'ProcessBulkActionBatch')) {
                return ['bulk-action'];
            }
        }
        
        return [];
    });
}
```

### Viewing Bulk Actions in Telescope

Access Telescope at `/telescope` and filter by:
- Type: "bulk-action"
- Tag: "bulk-action"
- Tag: "execution:{id}"

## Sentry Integration

### Installation

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=your-dsn-here
```

### Configuration

```php
// .env
SENTRY_LARAVEL_DSN=your-dsn-here
SENTRY_ENABLED=true
```

### Error Context

Automatically includes:
- Execution ID
- Action type
- Model class
- User information
- Stack traces

### Custom Tags

```php
use Sentry\State\Scope;

\Sentry\configureScope(function (Scope $scope) use ($execution) {
    $scope->setTag('component', 'action-engine');
    $scope->setTag('action_type', $execution->action_type);
    $scope->setContext('bulk_action', [
        'execution_id' => $execution->id,
        'total_records' => $execution->total_records,
    ]);
});
```

## Bugsnag Integration

### Installation

```bash
composer require bugsnag/bugsnag-laravel
php artisan vendor:publish --provider="Bugsnag\BugsnagLaravel\BugsnagServiceProvider"
```

### Configuration

```php
// .env
BUGSNAG_API_KEY=your-api-key
BUGSNAG_ENABLED=true
```

### Custom Metadata

```php
Bugsnag::registerCallback(function ($report) use ($execution) {
    $report->setMetaData([
        'bulk_action' => [
            'execution_id' => $execution->id,
            'action_type' => $execution->action_type,
            'processed' => $execution->processed_records,
            'total' => $execution->total_records,
        ]
    ]);
});
```

## Prometheus Metrics

### Installation

```bash
composer require jimdo/prometheus_client_php
```

### Exposed Metrics

```
# Total bulk actions executed
bulk_actions_total{action_type="update",status="completed"} 150

# Bulk action duration
bulk_action_duration_seconds{action_type="update"} 45.2

# Records processed
bulk_action_records_processed{action_type="delete"} 5000

# Failed executions
bulk_actions_failed_total{action_type="update"} 2

# Queue depth
bulk_action_queue_depth{queue="default"} 25
```

### Scrape Endpoint

```php
// routes/web.php
Route::get('/metrics', function () {
    $registry = \Prometheus\CollectorRegistry::getDefault();
    $renderer = new \Prometheus\RenderTextFormat();
    return response($renderer->render($registry->getMetricFamilySamples()))
        ->header('Content-Type', \Prometheus\RenderTextFormat::MIME_TYPE);
});
```

### Prometheus Configuration

```yaml
# prometheus.yml
scrape_configs:
  - job_name: 'laravel-action-engine'
    static_configs:
      - targets: ['your-app.com:80']
    metrics_path: '/metrics'
    scrape_interval: 15s
```

## Datadog Integration

### Installation

```bash
composer require datadog/php-datadogstatsd
```

### Configuration

```php
// .env
DATADOG_ENABLED=true
DATADOG_HOST=127.0.0.1
DATADOG_PORT=8125
```

### Custom Metrics

```php
use DataDog\DogStatsd;

$statsd = new DogStatsd([
    'host' => config('action-engine.monitoring.datadog.host'),
    'port' => config('action-engine.monitoring.datadog.port'),
]);

// Increment counter
$statsd->increment('bulk_action.executed', 1, [
    'action_type:update',
    'status:completed'
]);

// Record timing
$statsd->timing('bulk_action.duration', $duration, [
    'action_type:update'
]);

// Record gauge
$statsd->gauge('bulk_action.queue_depth', $queueSize);
```

## New Relic Integration

### Installation

```bash
# Install New Relic PHP agent
curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-X.X.X-linux.tar.gz | tar -xz
cd newrelic-php5-X.X.X-linux
sudo ./newrelic-install install
```

### Configuration

```php
// .env
NEWRELIC_ENABLED=true
NEWRELIC_APP_NAME="Laravel Action Engine"
```

### Custom Events

```php
if (extension_loaded('newrelic')) {
    // Record custom event
    newrelic_record_custom_event('BulkActionExecuted', [
        'execution_id' => $execution->id,
        'action_type' => $execution->action_type,
        'duration' => $duration,
        'records' => $execution->total_records,
    ]);
    
    // Record custom metric
    newrelic_custom_metric('Custom/BulkAction/Duration', $duration);
    newrelic_custom_metric('Custom/BulkAction/Records', $execution->total_records);
}
```

## Custom Logging

### Structured Logging

```php
// config/logging.php
'channels' => [
    'action-engine' => [
        'driver' => 'daily',
        'path' => storage_path('logs/action-engine.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 14,
    ],
    
    'action-engine-metrics' => [
        'driver' => 'single',
        'path' => storage_path('logs/action-engine-metrics.log'),
        'level' => 'info',
        'formatter' => JsonFormatter::class,
    ],
],
```

### Log Context

```php
Log::channel('action-engine')->info('Bulk action started', [
    'execution_id' => $execution->id,
    'action_type' => $execution->action_type,
    'user_id' => auth()->id(),
    'total_records' => $execution->total_records,
    'memory_start' => memory_get_usage(true),
    'timestamp' => now()->toIso8601String(),
]);
```

## Performance Monitoring

### Track Execution Time

```php
use Illuminate\Support\Facades\DB;

DB::listen(function ($query) {
    if ($query->time > 1000) { // Queries over 1 second
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
        ]);
    }
});
```

### Memory Monitoring

```php
$memoryStart = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);

Log::info('Memory usage', [
    'start' => round($memoryStart / 1024 / 1024, 2) . ' MB',
    'peak' => round($memoryPeak / 1024 / 1024, 2) . ' MB',
    'current' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
]);
```

## Alerting

### Queue Depth Alert

```php
use Illuminate\Support\Facades\Cache;

$queueSize = Queue::size();
$threshold = config('action-engine.monitoring.queue_threshold', 100);

if ($queueSize > $threshold) {
    // Send alert
    $this->sendAlert('High queue depth', [
        'current' => $queueSize,
        'threshold' => $threshold,
    ]);
}
```

### Failed Jobs Alert

```php
$failedCount = DB::table('failed_jobs')->count();

if ($failedCount > 10) {
    $this->sendAlert('Multiple failed jobs detected', [
        'count' => $failedCount,
    ]);
}
```

### Stuck Executions Alert

```php
$stuckExecutions = BulkActionExecution::where('status', 'processing')
    ->where('updated_at', '<', now()->subHour())
    ->count();

if ($stuckExecutions > 0) {
    $this->sendAlert('Stuck executions detected', [
        'count' => $stuckExecutions,
    ]);
}
```

## Health Check Endpoint

```php
// routes/api.php
Route::get('/health/action-engine', function () {
    $monitoring = app(MonitoringManager::class);
    $health = $monitoring->checkHealth();
    
    $isHealthy = $health['queue_depth'] < 100
        && $health['failed_jobs'] < 10
        && $health['stuck_executions'] === 0
        && $health['disk_usage'] < 90;
    
    return response()->json([
        'status' => $isHealthy ? 'healthy' : 'unhealthy',
        'checks' => $health,
        'timestamp' => now()->toIso8601String(),
    ], $isHealthy ? 200 : 503);
});
```

## Dashboard Examples

### Grafana Dashboard

```json
{
  "dashboard": {
    "title": "Laravel Action Engine",
    "panels": [
      {
        "title": "Execution Rate",
        "targets": [
          {
            "expr": "rate(bulk_actions_total[5m])"
          }
        ]
      },
      {
        "title": "Average Duration",
        "targets": [
          {
            "expr": "avg(bulk_action_duration_seconds)"
          }
        ]
      },
      {
        "title": "Error Rate",
        "targets": [
          {
            "expr": "rate(bulk_actions_failed_total[5m])"
          }
        ]
      }
    ]
  }
}
```

## Best Practices

1. **Enable monitoring in production** - Always monitor production environments
2. **Set up alerts** - Configure alerts for critical issues
3. **Regular health checks** - Schedule periodic health checks
4. **Log rotation** - Implement log rotation to manage disk space
5. **Performance baselines** - Establish performance baselines
6. **Error thresholds** - Set acceptable error rate thresholds
7. **Capacity planning** - Monitor trends for capacity planning
8. **Dashboard reviews** - Regularly review dashboards and metrics
