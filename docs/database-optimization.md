# Database Optimization Guide

## Recommended Indexes

Add these indexes to your database migrations for optimal performance.

### bulk_action_executions table

```php
Schema::table('bulk_action_executions', function (Blueprint $table) {
    // Performance indexes
    $table->index('status', 'idx_executions_status');
    $table->index('created_by', 'idx_executions_created_by');
    $table->index(['action_type', 'status'], 'idx_executions_action_status');
    $table->index('created_at', 'idx_executions_created_at');
    $table->index(['status', 'created_at'], 'idx_executions_status_created');
    
    // Composite index for common queries
    $table->index(['created_by', 'action_type', 'created_at'], 
        'idx_executions_user_action_date');
});
```

### bulk_action_progress table

```php
Schema::table('bulk_action_progress', function (Blueprint $table) {
    // Foreign key optimization
    $table->index('execution_id', 'idx_progress_execution');
    $table->index(['execution_id', 'status'], 'idx_progress_execution_status');
    $table->index(['execution_id', 'record_id'], 'idx_progress_execution_record');
    
    // For finding failed records
    $table->index(['execution_id', 'status', 'processed_at'], 
        'idx_progress_status_processed');
});
```

### bulk_action_undo table

```php
Schema::table('bulk_action_undo', function (Blueprint $table) {
    // Foreign key and lookup optimization
    $table->index('execution_id', 'idx_undo_execution');
    $table->index(['execution_id', 'record_id'], 'idx_undo_execution_record');
    $table->index(['model_type', 'record_id'], 'idx_undo_model_record');
    
    // For cleanup queries
    $table->index('created_at', 'idx_undo_created_at');
});
```

### bulk_action_audit table

```php
Schema::table('bulk_action_audit', function (Blueprint $table) {
    // Query optimization
    $table->index('execution_id', 'idx_audit_execution');
    $table->index('user_id', 'idx_audit_user');
    $table->index(['user_id', 'created_at'], 'idx_audit_user_date');
    $table->index('created_at', 'idx_audit_created_at');
    
    // For compliance queries
    $table->index(['action', 'created_at'], 'idx_audit_action_date');
});
```

## Table Partitioning

For high-volume environments, implement table partitioning to improve query performance and manage data lifecycle.

### Partition by Date (Recommended for Audit Logs)

```sql
-- MySQL 8.0+ example for audit table
ALTER TABLE bulk_action_audit
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### Partition by Hash (For Distributed Load)

```sql
-- Partition progress table by execution_id for distributed queries
ALTER TABLE bulk_action_progress
PARTITION BY HASH(execution_id)
PARTITIONS 4;
```

## Data Retention Policies

Implement automated cleanup to prevent table bloat.

### Create Cleanup Command

```php
<?php

namespace DhruvilNagar\ActionEngine\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CleanupOldData extends Command
{
    protected $signature = 'action-engine:cleanup {--days=30 : Days to retain}';
    protected $description = 'Clean up old execution data and expired undo records';

    public function handle(): int
    {
        $days = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up data older than {$cutoffDate->toDateString()}...");

        // Clean completed executions
        $deleted = DB::table('bulk_action_executions')
            ->where('status', 'completed')
            ->where('completed_at', '<', $cutoffDate)
            ->delete();
        $this->info("Deleted {$deleted} completed executions");

        // Clean expired undo records
        $undoTtl = config('action-engine.undo_ttl', 168); // hours
        $undoCutoff = Carbon::now()->subHours($undoTtl);
        $deletedUndo = DB::table('bulk_action_undo')
            ->where('created_at', '<', $undoCutoff)
            ->delete();
        $this->info("Deleted {$deletedUndo} expired undo records");

        // Archive old audit logs (optional)
        if (config('action-engine.archive_audit_logs', false)) {
            $this->archiveAuditLogs($cutoffDate);
        }

        $this->info('Cleanup completed successfully!');
        return 0;
    }

    protected function archiveAuditLogs(Carbon $cutoffDate): void
    {
        // Move to archive table or export to file
        DB::statement("
            INSERT INTO bulk_action_audit_archive
            SELECT * FROM bulk_action_audit
            WHERE created_at < ?
        ", [$cutoffDate]);

        DB::table('bulk_action_audit')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info('Audit logs archived');
    }
}
```

### Schedule Cleanup

```php
// In App\Console\Kernel
protected function schedule(Schedule $schedule)
{
    // Daily cleanup of old data
    $schedule->command('action-engine:cleanup --days=30')
        ->daily()
        ->at('02:00');
    
    // Weekly cleanup with longer retention
    $schedule->command('action-engine:cleanup --days=90')
        ->weekly()
        ->sundays()
        ->at('03:00');
}
```

## Snapshot Storage Optimization

### Compression Strategy

```php
<?php

namespace DhruvilNagar\ActionEngine\Support;

class SnapshotCompressor
{
    /**
     * Compress snapshot data before storage
     */
    public function compress(array $data): string
    {
        $serialized = serialize($data);
        $compressed = gzcompress($serialized, 9);
        return base64_encode($compressed);
    }

    /**
     * Decompress snapshot data after retrieval
     */
    public function decompress(string $compressedData): array
    {
        $decoded = base64_decode($compressedData);
        $decompressed = gzuncompress($decoded);
        return unserialize($decompressed);
    }

    /**
     * Calculate compression ratio
     */
    public function getCompressionRatio(array $data): float
    {
        $original = strlen(serialize($data));
        $compressed = strlen($this->compress($data));
        
        return round((1 - ($compressed / $original)) * 100, 2);
    }
}
```

### Deduplication

```php
<?php

namespace DhruvilNagar\ActionEngine\Support;

class SnapshotDeduplicator
{
    /**
     * Store only changed fields
     */
    public function storeChanges(array $original, array $modified): array
    {
        $changes = [];
        
        foreach ($modified as $key => $value) {
            if (!isset($original[$key]) || $original[$key] !== $value) {
                $changes[$key] = [
                    'old' => $original[$key] ?? null,
                    'new' => $value
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Reconstruct full record from changes
     */
    public function reconstruct(array $original, array $changes): array
    {
        $reconstructed = $original;
        
        foreach ($changes as $key => $change) {
            $reconstructed[$key] = $change['old'];
        }
        
        return $reconstructed;
    }
}
```

## Query Optimization Strategies

### Use Chunking for Large Datasets

```php
// Instead of this:
$users = User::where('active', false)->get();

// Use chunking:
User::where('active', false)->chunk(500, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});
```

### Use Cursor for Memory Efficiency

```php
// For very large datasets:
foreach (User::where('active', false)->cursor() as $user) {
    // Process user with minimal memory footprint
}
```

### Eager Load Relationships

```php
// Prevent N+1 queries
$executions = BulkActionExecution::with([
    'progress',
    'undoRecords' => function ($query) {
        $query->latest()->limit(10);
    }
])->get();
```

### Use Read Replicas

```php
// In config/database.php
'mysql' => [
    'read' => [
        'host' => env('DB_READ_HOST', '127.0.0.1'),
    ],
    'write' => [
        'host' => env('DB_WRITE_HOST', '127.0.0.1'),
    ],
    // ... other config
],

// Use in queries
DB::connection('mysql::read')->table('bulk_action_executions')->get();
```

## Cache Strategy

### Cache Progress Data

```php
<?php

namespace DhruvilNagar\ActionEngine\Support;

use Illuminate\Support\Facades\Cache;

class CachedProgressTracker
{
    protected int $ttl = 300; // 5 minutes

    public function getProgress(int $executionId): ?array
    {
        return Cache::remember(
            "bulk_action_progress.{$executionId}",
            $this->ttl,
            fn() => $this->fetchProgressFromDatabase($executionId)
        );
    }

    public function updateProgress(int $executionId, array $data): void
    {
        $this->updateDatabase($executionId, $data);
        
        // Invalidate cache
        Cache::forget("bulk_action_progress.{$executionId}");
    }

    protected function fetchProgressFromDatabase(int $executionId): array
    {
        return DB::table('bulk_action_executions')
            ->where('id', $executionId)
            ->first();
    }
}
```

### Cache Frequently Accessed Data

```php
// Cache action registry
Cache::remember('action_registry', 3600, function () {
    return ActionRegistry::all();
});

// Cache user permissions
Cache::tags(['user', "user.{$userId}"])
    ->remember("permissions.{$userId}", 3600, function () use ($userId) {
        return $this->fetchUserPermissions($userId);
    });
```

## Monitoring Queries

### Enable Query Logging

```php
// In development
DB::enableQueryLog();

// After operations
$queries = DB::getQueryLog();
foreach ($queries as $query) {
    Log::debug('Query', [
        'sql' => $query['query'],
        'bindings' => $query['bindings'],
        'time' => $query['time']
    ]);
}
```

### Add Query Performance Monitoring

```php
// In AppServiceProvider
DB::listen(function ($query) {
    if ($query->time > 1000) { // Queries slower than 1s
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings
        ]);
    }
});
```

## Database Connection Pooling

```php
// In config/database.php
'mysql' => [
    // ... other config
    'options' => [
        PDO::ATTR_PERSISTENT => true, // Enable persistent connections
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
    'pool' => [
        'min' => 2,
        'max' => 10,
    ],
],
```

## Performance Benchmarks

### Expected Query Times

| Operation | Records | Expected Time | Notes |
|-----------|---------|---------------|-------|
| Create execution | 1 | < 50ms | With indexes |
| Update progress | 1 | < 10ms | Cached |
| Fetch execution status | 1 | < 20ms | With indexes |
| Bulk insert progress | 500 | < 200ms | Batch insert |
| Undo operation | 100 | < 500ms | With snapshot decompression |

### Optimization Checklist

- [ ] All foreign keys indexed
- [ ] Composite indexes for common query patterns
- [ ] Partitioning enabled for large tables
- [ ] Data retention policy configured
- [ ] Snapshot compression enabled
- [ ] Query caching implemented
- [ ] Read replicas configured (production)
- [ ] Slow query logging enabled
- [ ] Regular ANALYZE TABLE maintenance
- [ ] Connection pooling configured

## Maintenance Scripts

### Analyze Tables

```bash
#!/bin/bash
# Run weekly to optimize query planner

mysql -u root -p your_database << EOF
ANALYZE TABLE bulk_action_executions;
ANALYZE TABLE bulk_action_progress;
ANALYZE TABLE bulk_action_undo;
ANALYZE TABLE bulk_action_audit;
EOF
```

### Optimize Tables

```bash
#!/bin/bash
# Run monthly to reclaim space

mysql -u root -p your_database << EOF
OPTIMIZE TABLE bulk_action_executions;
OPTIMIZE TABLE bulk_action_progress;
OPTIMIZE TABLE bulk_action_undo;
OPTIMIZE TABLE bulk_action_audit;
EOF
```

## Migration Path

If adding indexes to existing large tables, use online DDL:

```sql
-- MySQL 8.0+ online DDL
ALTER TABLE bulk_action_executions
    ADD INDEX idx_status (status) ALGORITHM=INPLACE, LOCK=NONE;
```

Or create indexes during off-peak hours with pt-online-schema-change:

```bash
pt-online-schema-change \
    --alter "ADD INDEX idx_status (status)" \
    D=database,t=bulk_action_executions \
    --execute
```
