# Performance Tuning Guide

## Overview

This guide provides recommendations for optimizing Laravel Action Engine performance in production environments.

## Memory Optimization

### Batch Size Configuration

```php
// Adjust based on available memory
BulkAction::on(Model::class)
    ->batchSize(500) // Default
    ->execute();

// For memory-constrained environments
BulkAction::on(Model::class)
    ->batchSize(100)
    ->execute();

// For high-memory servers
BulkAction::on(Model::class)
    ->batchSize(2000)
    ->execute();
```

### Memory Requirements by Batch Size

| Batch Size | Estimated Memory | Recommended For |
|------------|------------------|-----------------|
| 100 | 32 MB | Small VPS (512MB RAM) |
| 500 | 128 MB | Standard servers (2GB RAM) |
| 1000 | 256 MB | Medium servers (4GB+ RAM) |
| 2000 | 512 MB | Large servers (8GB+ RAM) |

### Use Lazy Collections

```php
Model::where('active', false)
    ->lazy()
    ->each(function ($model) {
        // Process with minimal memory
    });
```

## Queue Configuration

### Worker Optimization

```bash
# Single worker
php artisan queue:work --sleep=3 --tries=3

# Multiple workers for better throughput
php artisan queue:work --sleep=1 --tries=3 &
php artisan queue:work --sleep=1 --tries=3 &
php artisan queue:work --sleep=1 --tries=3 &

# With Supervisor
[program:action-engine-worker]
command=php /path/to/artisan queue:work --queue=bulk-actions --sleep=3 --tries=3 --max-time=3600
numprocs=3
```

### Queue Priority

```php
// config/action-engine.php
'queue' => [
    'connection' => 'redis',
    'queue' => 'bulk-actions',
    'priority' => [
        'high' => 10,
        'normal' => 5,
        'low' => 1,
    ],
],
```

## Database Performance

### Connection Pooling

```php
// config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
    'pool' => [
        'min' => 2,
        'max' => 10,
    ],
],
```

### Query Optimization

```php
// Use select() to limit columns
Model::select(['id', 'name', 'email'])
    ->where('active', false)
    ->chunk(500, function ($models) {
        // Process
    });

// Use raw queries for simple updates
DB::update('UPDATE users SET active = 1 WHERE id IN (?)', [$ids]);
```

## Caching Strategy

### Cache Configuration

```php
// config/action-engine.php
'cache' => [
    'enabled' => true,
    'ttl' => 300, // 5 minutes
    'store' => 'redis',
    'prefix' => 'action_engine:',
],
```

### Cache Progress Updates

```php
Cache::put(
    "execution:{$executionId}:progress",
    $progressData,
    now()->addMinutes(5)
);
```

## Broadcasting Optimization

### Throttle Updates

```php
// Only broadcast every 2 seconds
$lastBroadcast = Cache::get("execution:{$id}:last_broadcast");

if (!$lastBroadcast || $lastBroadcast->diffInSeconds(now()) >= 2) {
    event(new BulkActionProgress($data));
    Cache::put("execution:{$id}:last_broadcast", now(), 60);
}
```

## Production Recommendations

### Hardware Requirements

**Minimum:**
- 2 CPU cores
- 2GB RAM
- 20GB SSD storage

**Recommended:**
- 4+ CPU cores
- 8GB+ RAM
- 50GB+ SSD storage
- Redis for caching and queues

### Server Configuration

```ini
; php.ini
memory_limit = 512M
max_execution_time = 300
upload_max_filesize = 20M
post_max_size = 20M

; opcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
```

### Laravel Configuration

```php
// config/app.php
'env' => 'production',
'debug' => false,

// config/cache.php
'default' => 'redis',

// config/queue.php
'default' => 'redis',

// config/session.php
'driver' => 'redis',
```

## Benchmarks

### Performance Targets

| Operation | Target | Notes |
|-----------|--------|-------|
| Queue job | < 100ms | Per batch |
| Progress update | < 50ms | Cached |
| Undo operation | < 1s | Per 100 records |
| Export | < 5s | Per 10,000 records |

### Load Testing

```bash
# Install Apache Bench
apt-get install apache2-utils

# Test endpoint
ab -n 1000 -c 10 http://your-app.com/api/bulk-actions
```

## Scaling Strategies

### Horizontal Scaling

1. **Multiple queue workers across servers**
2. **Load balancer for web requests**
3. **Database read replicas**
4. **Distributed Redis cluster**

### Vertical Scaling

1. **Increase server resources**
2. **Optimize batch sizes**
3. **Enable OPcache**
4. **Use faster storage (NVMe SSD)**

## Monitoring Performance

```php
// Log slow operations
$start = microtime(true);
// ... operation
$duration = microtime(true) - $start;

if ($duration > 1.0) {
    Log::warning('Slow operation', [
        'duration' => $duration,
        'operation' => 'bulk_action',
    ]);
}
```
