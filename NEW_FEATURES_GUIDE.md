# New Features Quick Start Guide

This guide covers the new features and improvements added to Laravel Action Engine.

## Table of Contents
- [Memory Optimization](#memory-optimization)
- [New Export Formats](#new-export-formats)
- [Enhanced Monitoring](#enhanced-monitoring)
- [Improved Error Handling](#improved-error-handling)
- [Better Cleanup Tools](#better-cleanup-tools)

---

## Memory Optimization

### Automatic Memory Management

The package now includes intelligent memory optimization:

```php
use DhruvilNagar\ActionEngine\Support\MemoryOptimizer;

$optimizer = new MemoryOptimizer();

// Check current memory status
$usage = $optimizer->getCurrentMemoryUsage(); // 0.65 (65%)
$formatted = $optimizer->getCurrentMemoryFormatted(); // "128.5 MB"

// Get recommendations
$recommended = $optimizer->getRecommendedBatchSize(1000);

// Optimize before processing
$optimizer->optimizeBeforeBatch();
```

### Configuration

```php
// config/action-engine.php
'performance' => [
    'memory_threshold' => 0.8,        // Optimize at 80% usage
    'auto_adjust_batch_size' => true, // Automatic adjustment
    'gc_threshold' => 0.75,           // GC at 75%
    'clear_query_log' => true,        // Clear logs
],
```

### In Bulk Actions

Memory optimization is automatically applied:

```php
BulkAction::on(Product::class)
    ->action('update')
    ->where('status', 'draft')
    ->parameters(['status' => 'published'])
    ->execute(); // Automatically optimizes memory
```

---

## New Export Formats

### XML Export

```php
BulkAction::on(Product::class)
    ->where('status', 'active')
    ->export('xml', [
        'root_element' => 'products',
        'row_element' => 'product',
        'format_output' => true,
        'metadata' => [
            'exported_by' => auth()->user()->name,
            'exported_at' => now()->toDateTimeString(),
        ],
    ]);
```

**Output:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<products>
  <metadata>
    <exported_by>John Doe</exported_by>
    <exported_at>2026-01-28 10:30:00</exported_at>
  </metadata>
  <product>
    <id>1</id>
    <name>Product Name</name>
    <status>active</status>
  </product>
  <!-- More products -->
</products>
```

### JSON Streaming Export

For large datasets, use streaming to avoid memory issues:

```php
// Standard JSON
BulkAction::on(Product::class)
    ->where('status', 'active')
    ->export('json', [
        'pretty_print' => true,
        'include_metadata' => true,
        'include_summary' => true,
    ]);

// JSON Lines (one JSON object per line)
BulkAction::on(Product::class)
    ->where('status', 'active')
    ->export('jsonl'); // or 'ndjson'
```

### Custom Streaming

```php
use DhruvilNagar\ActionEngine\Support\ExportDrivers\JsonStreamExportDriver;

$driver = new JsonStreamExportDriver();

$driver->streamExport(
    function () {
        // Yield chunks of data
        yield Product::where('status', 'active')->limit(1000)->get();
        yield Product::where('status', 'pending')->limit(1000)->get();
    },
    'products-export.json',
    ['pretty_print' => true]
);
```

---

## Enhanced Monitoring

### CLI Monitoring

Real-time monitoring from the command line:

```bash
# One-time snapshot
php artisan action-engine:monitor

# Continuous monitoring (refreshes every 5 seconds)
php artisan action-engine:monitor --watch

# Detailed view with active actions
php artisan action-engine:monitor --detailed

# Custom refresh interval
php artisan action-engine:monitor --watch --interval=3
```

**Output:**
```
📊 Overview
┌─────────────────────────────┬─────────┐
│ Total Executions            │ 12,345  │
│ Active (Pending+Processing) │ 5       │
│ Completed                   │ 11,890  │
│ Failed                      │ 450     │
└─────────────────────────────┴─────────┘

⚡ Performance Metrics
Average throughput: 1,234.56 records/second
```

### Dashboard API

Use the REST API for custom dashboards:

```javascript
// Overview
fetch('/bulk-actions/monitoring/overview?range=24h')
  .then(res => res.json())
  .then(data => {
    console.log(data.stats);
    console.log(data.chart_data);
  });

// Real-time metrics
fetch('/bulk-actions/monitoring/metrics')
  .then(res => res.json())
  .then(data => {
    console.log('Active:', data.active);
    console.log('Performance:', data.performance);
  });

// Health check
fetch('/bulk-actions/monitoring/health')
  .then(res => res.json())
  .then(data => {
    console.log('Status:', data.status);
    console.log('Checks:', data.checks);
  });
```

### Available Endpoints

```
GET /bulk-actions/monitoring/overview          # Dashboard overview
GET /bulk-actions/monitoring/metrics           # Real-time metrics
GET /bulk-actions/monitoring/health            # System health
GET /bulk-actions/monitoring/action-breakdown  # Action statistics
GET /bulk-actions/monitoring/user-activity     # User activity
GET /bulk-actions/monitoring/performance-trends # Trends over time
```

---

## Improved Error Handling

### New Exception Classes

```php
use DhruvilNagar\ActionEngine\Exceptions\ActionExecutionException;
use DhruvilNagar\ActionEngine\Exceptions\ExportException;
use DhruvilNagar\ActionEngine\Exceptions\QueueException;

try {
    $execution = BulkAction::on(Product::class)
        ->action('update')
        ->execute();
        
} catch (ActionExecutionException $e) {
    // Record-level error
    $recordId = $e->getRecordId();
    $batchNumber = $e->getBatchNumber();
    
    if ($e->isRetryable()) {
        // Retry logic
    }
    
} catch (ExportException $e) {
    // Export-specific error
    Log::error('Export failed: ' . $e->getMessage());
    
} catch (QueueException $e) {
    // Queue-related error
    Log::error('Queue issue: ' . $e->getMessage());
}
```

### Exception Examples

```php
// Record failed
throw ActionExecutionException::recordFailed(123, 'update', $previous);

// Batch failed
throw ActionExecutionException::batchFailed(5, 'delete', 10);

// Constraint violation
throw ActionExecutionException::constraintViolation('fk_constraint', 'delete');

// Timeout
throw ActionExecutionException::timeout('bulk-update', 300);

// Memory exceeded
throw ActionExecutionException::memoryExceeded('bulk-update', '512M');

// Export driver not found
throw ExportException::driverNotFound('excel', 'maatwebsite/excel');

// Queue connection failed
throw QueueException::connectionFailed('redis', 'Connection refused');
```

---

## Better Cleanup Tools

### Enhanced Cleanup Command

```bash
# Preview what would be deleted (dry run)
php artisan action-engine:cleanup --dry-run

# Clean only expired undo records
php artisan action-engine:cleanup --expired

# Clean old completed executions (default: 30 days)
php artisan action-engine:cleanup --old

# Clean with custom retention period
php artisan action-engine:cleanup --old --days=7

# Clean old failed executions
php artisan action-engine:cleanup --failed --days=14

# Clean old audit logs
php artisan action-engine:cleanup --audit --days=90

# Force without confirmation
php artisan action-engine:cleanup --force

# Run synchronously (not in queue)
php artisan action-engine:cleanup --sync
```

### Cleanup Output

```
🧹 Starting Action Engine cleanup...

┌─────────────────────────────┬─────────┐
│ Category                    │ Count   │
├─────────────────────────────┼─────────┤
│ Expired undo records        │ 1,234   │
│ Old completed executions    │ 567     │
│ Old failed executions       │ 89      │
│ Old audit logs              │ 4,321   │
│ Orphaned progress records   │ 12      │
├─────────────────────────────┼─────────┤
│ Total records to clean      │ 6,223   │
└─────────────────────────────┴─────────┘

Do you want to proceed with cleanup? (yes/no) [no]:
```

### Scheduled Cleanup

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily cleanup of expired data
    $schedule->command('action-engine:cleanup --expired --force')
             ->daily()
             ->at('02:00');
    
    // Weekly cleanup of old completed executions
    $schedule->command('action-engine:cleanup --old --days=30 --force')
             ->weekly()
             ->sundays()
             ->at('03:00');
    
    // Monthly cleanup of audit logs
    $schedule->command('action-engine:cleanup --audit --days=90 --force')
             ->monthly()
             ->at('04:00');
}
```

---

## Livewire Components

### Using the Stubs

Publish Livewire stubs:

```bash
php artisan vendor:publish --tag=action-engine-stubs
```

This creates:
- `stubs/livewire/BulkActionManager.stub`
- `stubs/livewire/BulkActionProgress.stub`
- `stubs/livewire/BulkActionHistory.stub`

### Example Usage

```php
// In your controller or component
use App\Livewire\ActionEngine\BulkActionManager;

public function render()
{
    return view('livewire.products-list');
}
```

```blade
{{-- In your view --}}
<div>
    @livewire('action-engine.bulk-action-manager', [
        'modelClass' => \App\Models\Product::class
    ])
</div>
```

---

## Filament Integration

### Using the Stubs

Publish Filament stubs:

```bash
php artisan vendor:publish --tag=action-engine-stubs
```

### Create Custom Bulk Action

```php
use Filament\Tables\Actions\BulkAction;
use DhruvilNagar\ActionEngine\Facades\ActionEngine;

BulkAction::make('publish')
    ->label('Publish Selected')
    ->icon('heroicon-o-check')
    ->requiresConfirmation()
    ->action(function (Collection $records) {
        ActionEngine::on(Product::class)
            ->action('update')
            ->whereIn('id', $records->pluck('id'))
            ->parameters(['status' => 'published'])
            ->withProgress()
            ->execute();
    });
```

### Add Resource

Use the `BulkActionResource.stub` to create a full Filament resource for managing bulk actions.

### Add Dashboard Widget

Use the `BulkActionStatsWidget.stub` for dashboard statistics.

---

## Testing

Run the new test suites:

```bash
# All tests
php artisan test

# Exception handling tests
php artisan test --filter=ExceptionHandlingTest

# Memory optimizer tests
php artisan test --filter=MemoryOptimizerTest

# Integration tests
php artisan test --filter=ErrorHandlingIntegrationTest
php artisan test --filter=QueueIntegrationTest
```

---

## Need Help?

- 📖 [Full Documentation](https://github.com/dhruvilnagar/laravel-action-engine)
- 🐛 [Report Issues](https://github.com/dhruvilnagar/laravel-action-engine/issues)
- 💬 [Discussions](https://github.com/dhruvilnagar/laravel-action-engine/discussions)

---

## Upgrade Guide

If upgrading from a previous version:

1. Update composer:
   ```bash
   composer update dhruvilnagar/laravel-action-engine
   ```

2. Publish new config:
   ```bash
   php artisan vendor:publish --tag=action-engine-config --force
   ```

3. Review new configuration options in `config/action-engine.php`

4. Test your existing bulk actions to ensure compatibility

**Note:** All changes are backward compatible. No breaking changes.
