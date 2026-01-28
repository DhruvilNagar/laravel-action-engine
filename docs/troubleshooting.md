# Troubleshooting Guide

## Common Issues and Solutions

### Queue Issues

#### Problem: Jobs are not being processed

**Symptoms:**
- Executions stuck in "queued" status
- No progress updates
- Queue worker not running

**Solutions:**

1. **Check if queue worker is running:**
```bash
php artisan queue:work
# or
php artisan queue:listen
```

2. **Check queue configuration:**
```php
// config/action-engine.php
'queue' => 'default', // Make sure this queue exists
```

3. **Verify queue driver is configured:**
```bash
# .env
QUEUE_CONNECTION=database  # or redis, sqs, etc.
```

4. **Check failed jobs:**
```bash
php artisan queue:failed
php artisan queue:retry all
```

5. **Monitor queue depth:**
```bash
php artisan queue:work --once --verbose
```

---

#### Problem: Jobs failing silently

**Symptoms:**
- Executions marked as "failed" without error messages
- No stack traces in logs

**Solutions:**

1. **Enable detailed logging:**
```php
// config/logging.php
'channels' => [
    'action-engine' => [
        'driver' => 'daily',
        'path' => storage_path('logs/action-engine.log'),
        'level' => 'debug',
    ],
],
```

2. **Check for timeout issues:**
```php
// In Job class
public $timeout = 300; // Increase timeout
public $tries = 3;     // Allow retries
```

3. **Add exception handling:**
```php
try {
    // Your action logic
} catch (\Exception $e) {
    Log::error('Bulk action failed', [
        'execution_id' => $this->executionId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    throw $e;
}
```

---

### Memory Issues

#### Problem: Out of memory errors during large operations

**Symptoms:**
- "Allowed memory size exhausted" error
- Process killed by system
- Slow performance

**Solutions:**

1. **Reduce batch size:**
```php
BulkAction::on(Model::class)
    ->batchSize(100) // Reduce from default 500
    ->execute();
```

2. **Use chunking:**
```php
Model::where('condition', true)
    ->chunk(100, function ($records) {
        // Process in smaller chunks
    });
```

3. **Use cursor for iteration:**
```php
foreach (Model::cursor() as $record) {
    // Minimal memory footprint
}
```

4. **Increase PHP memory limit:**
```php
// config/action-engine.php
ini_set('memory_limit', '512M');

// Or in .env for all processes
// memory_limit = 512M
```

5. **Use lazy loading:**
```php
Model::lazy()->each(function ($record) {
    // Process one at a time
});
```

---

### Database Issues

#### Problem: Slow queries during bulk operations

**Symptoms:**
- Long execution times
- Database CPU at 100%
- Query timeouts

**Solutions:**

1. **Add missing indexes:**
```bash
php artisan migrate:refresh
# Ensure all recommended indexes from database-optimization.md are added
```

2. **Analyze query performance:**
```php
DB::enableQueryLog();
// ... run your operation
$queries = DB::getQueryLog();
dd($queries);
```

3. **Use database transactions efficiently:**
```php
DB::transaction(function () {
    // Group related operations
}, 3); // 3 retry attempts
```

4. **Optimize with eager loading:**
```php
$executions = BulkActionExecution::with(['progress', 'undoRecords'])
    ->get();
```

5. **Use raw queries for complex operations:**
```php
DB::statement('UPDATE users SET active = 1 WHERE id IN (?)', [$ids]);
```

---

#### Problem: Deadlocks during concurrent operations

**Symptoms:**
- "Deadlock found when trying to get lock" error
- Random operation failures

**Solutions:**

1. **Use consistent locking order:**
```php
// Always lock records in the same order (e.g., by ID ascending)
$records = Model::whereIn('id', $ids)
    ->orderBy('id')
    ->lockForUpdate()
    ->get();
```

2. **Reduce transaction scope:**
```php
// Process smaller batches
foreach (array_chunk($ids, 50) as $batch) {
    DB::transaction(function () use ($batch) {
        // Process batch
    });
}
```

3. **Add retry logic:**
```php
DB::transaction(function () {
    // Your logic
}, 5); // Retry up to 5 times
```

4. **Use pessimistic locking:**
```php
BulkAction::on(Model::class)
    ->withRecordLocking()
    ->execute();
```

---

### Progress Tracking Issues

#### Problem: Progress updates not showing

**Symptoms:**
- UI stuck at 0%
- No real-time updates
- Progress bar not moving

**Solutions:**

1. **Check broadcasting configuration:**
```php
// config/broadcasting.php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        // ...
    ],
],
```

2. **Verify event listeners:**
```bash
php artisan event:list
# Check if BulkActionProgress event is registered
```

3. **Enable polling as fallback:**
```javascript
// In frontend
setInterval(() => {
    fetch(`/api/bulk-actions/${executionId}/progress`)
        .then(response => response.json())
        .then(data => updateProgress(data));
}, 2000); // Poll every 2 seconds
```

4. **Check queue is processing:**
```bash
php artisan queue:work --verbose
```

5. **Verify event is being dispatched:**
```php
// Add logging to event
Log::info('Progress event dispatched', [
    'execution_id' => $this->executionId,
    'percentage' => $this->percentage
]);
```

---

### Undo Functionality Issues

#### Problem: Undo operation fails

**Symptoms:**
- "Undo expired" error
- Records not restored
- Partial undo completion

**Solutions:**

1. **Check undo TTL:**
```php
// config/action-engine.php
'undo_ttl' => 168, // hours (7 days)
```

2. **Verify undo data exists:**
```php
$undoRecords = BulkActionUndo::where('execution_id', $executionId)->get();
if ($undoRecords->isEmpty()) {
    Log::error('No undo data found for execution', ['id' => $executionId]);
}
```

3. **Check for compressed data issues:**
```php
try {
    $data = gzuncompress(base64_decode($snapshot));
} catch (\Exception $e) {
    Log::error('Failed to decompress undo data', [
        'execution_id' => $executionId,
        'error' => $e->getMessage()
    ]);
}
```

4. **Ensure undo is enabled:**
```php
BulkAction::on(Model::class)
    ->withUndo() // Make sure this is called
    ->execute();
```

---

### Authorization Issues

#### Problem: Unauthorized bulk action exceptions

**Symptoms:**
- UnauthorizedBulkActionException thrown
- Actions blocked for certain users
- Policy checks failing

**Solutions:**

1. **Check policy registration:**
```php
// App\Providers\AuthServiceProvider
protected $policies = [
    User::class => UserPolicy::class,
];
```

2. **Verify policy methods:**
```php
// App\Policies\UserPolicy
public function bulkUpdate(User $user)
{
    return $user->hasPermission('users.bulk-update');
}
```

3. **Debug policy resolution:**
```php
$user = auth()->user();
$can = Gate::allows('bulkUpdate', User::class);
Log::debug('Policy check', ['user' => $user->id, 'can' => $can]);
```

4. **Bypass authorization for testing:**
```php
BulkAction::on(Model::class)
    ->skipAuthorization() // Only for testing!
    ->execute();
```

---

### Rate Limiting Issues

#### Problem: Rate limit exceeded errors

**Symptoms:**
- RateLimitExceededException thrown
- Operations blocked
- "Too many requests" messages

**Solutions:**

1. **Adjust rate limits:**
```php
// config/action-engine.php
'rate_limit' => [
    'per_user' => 100,     // per hour
    'global' => 1000,      // per hour
],
```

2. **Implement backoff strategy:**
```php
use Illuminate\Support\Facades\RateLimiter;

if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
    $seconds = RateLimiter::availableIn($key);
    throw new RateLimitExceededException(
        "Too many attempts. Retry in {$seconds} seconds."
    );
}
```

3. **Clear rate limiter manually:**
```bash
php artisan cache:clear
# Or programmatically:
RateLimiter::clear($key);
```

4. **Use different keys for different operations:**
```php
$key = 'bulk-action:' . auth()->id() . ':' . $actionType;
```

---

### Export Issues

#### Problem: Export generation fails

**Symptoms:**
- Empty files generated
- Timeout errors
- Memory exhausted

**Solutions:**

1. **Use streaming for large exports:**
```php
return response()->streamDownload(function () {
    $records = Model::cursor();
    foreach ($records as $record) {
        echo $this->formatRecord($record);
    }
}, 'export.csv');
```

2. **Process exports in background:**
```php
BulkAction::on(Model::class)
    ->export('csv')
    ->queue() // Process asynchronously
    ->execute();
```

3. **Limit export size:**
```php
// config/action-engine.php
'export' => [
    'max_records' => 100000,
    'chunk_size' => 1000,
],
```

4. **Check disk space:**
```bash
df -h
# Ensure sufficient space in storage/app
```

---

## Performance Optimization

### Slow Execution Times

1. **Profile your code:**
```bash
composer require --dev barryvdh/laravel-debugbar
```

2. **Add timing logs:**
```php
$start = microtime(true);
// ... operation
$duration = microtime(true) - $start;
Log::info('Operation duration', ['seconds' => $duration]);
```

3. **Use Laravel Telescope:**
```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

4. **Check query count:**
```php
DB::enableQueryLog();
// ... operation
Log::info('Query count', ['count' => count(DB::getQueryLog())]);
```

---

## Debugging Tools

### Enable Debug Mode

```php
// .env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Useful Artisan Commands

```bash
# Check queue status
php artisan queue:work --once --verbose

# List all events and listeners
php artisan event:list

# Clear all caches
php artisan optimize:clear

# Run specific test
php artisan test --filter=BulkActionTest

# Check failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry <job-id>

# Monitor queue in real-time
php artisan queue:monitor redis:default --max=100
```

### Log Analysis

```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Search for errors
grep -i "error" storage/logs/laravel.log

# Count occurrences
grep -c "BulkAction" storage/logs/laravel.log

# Find slow queries
grep "time=" storage/logs/laravel.log | sort -t= -k2 -n
```

---

## Health Checks

### System Health Verification

```php
<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthCheck extends Command
{
    protected $signature = 'action-engine:health';
    protected $description = 'Check system health';

    public function handle(): int
    {
        $this->info('Running health checks...');

        // Database connectivity
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database connection OK');
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: ' . $e->getMessage());
        }

        // Queue connectivity
        try {
            $size = Queue::size();
            $this->info("✓ Queue OK (size: {$size})");
        } catch (\Exception $e) {
            $this->error('✗ Queue check failed: ' . $e->getMessage());
        }

        // Redis connectivity (if used)
        if (config('cache.default') === 'redis') {
            try {
                Redis::ping();
                $this->info('✓ Redis connection OK');
            } catch (\Exception $e) {
                $this->error('✗ Redis connection failed: ' . $e->getMessage());
            }
        }

        // Disk space
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $percentage = round(($free / $total) * 100, 2);
        
        if ($percentage < 10) {
            $this->warn("⚠ Low disk space: {$percentage}% free");
        } else {
            $this->info("✓ Disk space OK: {$percentage}% free");
        }

        // Memory
        $memory = memory_get_usage(true) / 1024 / 1024;
        $this->info("✓ Memory usage: " . round($memory, 2) . " MB");

        return 0;
    }
}
```

---

## Getting Help

### Before Requesting Support

1. Check this troubleshooting guide
2. Review the [documentation](../README.md)
3. Search existing [GitHub issues](https://github.com/dhruvilnagar/laravel-action-engine/issues)
4. Enable debug mode and collect logs

### When Reporting Issues

Include:
- Laravel version
- PHP version
- Package version
- Full error message and stack trace
- Steps to reproduce
- Relevant configuration
- Sample code

### Support Channels

- GitHub Issues: https://github.com/dhruvilnagar/laravel-action-engine/issues
- Email: ddhruvill264@gmail.com

---

## Emergency Recovery

### Stuck Executions

```sql
-- Find stuck executions
SELECT * FROM bulk_action_executions 
WHERE status = 'processing' 
AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Mark as failed
UPDATE bulk_action_executions 
SET status = 'failed', error_message = 'Timeout - recovered manually'
WHERE id IN (/* stuck execution IDs */);
```

### Clear All Queued Jobs

```bash
# Redis
redis-cli FLUSHDB

# Database
php artisan queue:flush
```

### Reset Everything

```bash
php artisan action-engine:cleanup --days=0
php artisan queue:flush
php artisan cache:clear
php artisan optimize:clear
```
