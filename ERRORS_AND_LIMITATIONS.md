# Errors, Limitations & Known Issues

This document comprehensively documents all known errors, limitations, edge cases, and potential issues with the Laravel Action Engine package.

## Table of Contents
1. [Installation Errors](#installation-errors)
2. [Configuration Issues](#configuration-issues)
3. [Runtime Errors](#runtime-errors)
4. [Performance Limitations](#performance-limitations)
5. [Feature Limitations](#feature-limitations)
6. [Database-Related Issues](#database-related-issues)
7. [Queue-Related Issues](#queue-related-issues)
8. [Export Issues](#export-issues)
9. [Frontend Integration Issues](#frontend-integration-issues)
10. [Workarounds & Solutions](#workarounds--solutions)

---

## Installation Errors

### ~~Error 1: Missing Livewire/Filament Stubs~~ ✅ FIXED
**Previous Error Message:**
```
ERROR  Can't locate path: <vendor/dhruvilnagar/laravel-action-engine/src/../stubs/livewire>
ERROR  Can't locate path: <vendor/dhruvilnagar/laravel-action-engine/src/../stubs/filament>
```

**Status:** ✅ RESOLVED
- Livewire stubs now available at `stubs/livewire/`
  - `BulkActionManager.stub` - Main action manager component
  - `BulkActionProgress.stub` - Progress tracking component
  - `BulkActionHistory.stub` - History viewer component
- Filament stubs now available at `stubs/filament/`
  - `BulkAction.stub` - Custom bulk action template
  - `BulkActionResource.stub` - Full resource with CRUD
  - `BulkActionStatsWidget.stub` - Statistics dashboard widget

---

### ~~Error 2: Laravel Version Compatibility~~ ✅ FIXED
**Previous Error Message:**
```
Problem 1
- dhruvilnagar/laravel-action-engine v1.0.0 requires illuminate/contracts ^10.0|^11.0
```

**Status:** ✅ RESOLVED
- Package now supports Laravel 10, 11, and 12+
- PHP 8.1, 8.2, and 8.3 supported
- Updated composer.json with latest version constraints

---

### Error 3: PHP Version Requirement
**Requirements:**
- PHP 8.1 or higher required

**When it occurs:**
- Installing on PHP 8.0 or lower

**Impact:**
- Package uses PHP 8.1+ features and will not work on older versions

**Workaround:**
- Upgrade PHP to 8.1 or higher

---

## Configuration Issues

### Issue 1: Queue Connection Not Configured
**Error Message:**
```
Queue connection [redis] not configured
```

**When it occurs:**
- When queue connection specified in config doesn't exist
- When using ACTION_ENGINE_QUEUE_CONNECTION without proper setup

**Workaround:**
```php
// config/action-engine.php
'queue' => [
    'connection' => null, // Falls back to default
    'name' => 'default',
],
```

---

### Issue 2: Broadcasting Not Working
**Symptoms:**
- Real-time progress updates don't appear
- WebSocket events not firing

**Common Causes:**
1. Broadcasting driver not configured
2. Echo not installed/configured in frontend
3. Queue workers not running

**Workaround:**
```php
// Disable broadcasting if not needed
// config/action-engine.php
'broadcasting' => [
    'enabled' => false,
],
```

---

### Issue 3: Rate Limiting Too Restrictive
**Symptoms:**
- `RateLimitExceededException` thrown frequently
- Users cannot perform bulk actions

**Workaround:**
```php
// config/action-engine.php
'rate_limiting' => [
    'enabled' => false, // Disable temporarily
    // OR increase limits
    'max_concurrent_actions' => 10,
],
```

---

## Runtime Errors

### Error 1: Action Not Registered
**Error Message:**
```
InvalidActionException: Action 'custom-action' is not registered
```

**When it occurs:**
- Using an action name that hasn't been registered

**Solution:**
```php
// app/Providers/AppServiceProvider.php
use DhruvilNagar\ActionEngine\Facades\ActionRegistry;

public function boot()
{
    ActionRegistry::register('custom-action', function ($record, $params) {
        // Your logic
        return true;
    });
}
```

---

### Error 2: Model Class Not Found
**Error Message:**
```
Class 'App\Models\YourModel' not found
```

**When it occurs:**
- Using incorrect model class name
- Model doesn't exist

**Solution:**
```php
// Use correct fully qualified class name
BulkAction::on(\App\Models\Product::class)
    ->action('update')
    ->execute();
```

---

### Error 3: Undo Not Available
**Error Message:**
```
Cannot undo action: Undo data expired or not available
```

**When it occurs:**
- Undo expiry period passed
- Action doesn't support undo
- Undo disabled in config

**Causes:**
1. Undo data cleaned up (expired)
2. Action created without `withUndo()`
3. Action type doesn't support undo

**Check Before Undo:**
```php
$execution = BulkActionExecution::where('uuid', $uuid)->first();

if (!$execution->canUndo()) {
    return 'Cannot undo this action';
}
```

---

### Error 4: Memory Limit Exceeded
**Error Message:**
```
PHP Fatal error: Allowed memory size exhausted
```

**When it occurs:**
- Processing very large datasets
- Batch size too large
- Loading too many records in memory

**Solutions:**
```php
// Reduce batch size
BulkAction::on(Product::class)
    ->action('update')
    ->batchSize(100) // Smaller batches
    ->execute();

// OR increase PHP memory limit
ini_set('memory_limit', '512M');

// OR in config
'performance' => [
    'chunk_size' => 50,
    'memory_limit' => '512M',
],
```

---

### Error 5: Timeout Exceeded
**Error Message:**
```
Maximum execution time of 30 seconds exceeded
```

**When it occurs:**
- Synchronous execution of large bulk actions
- Long-running custom actions

**Solution:**
```php
// Use async (queue) instead of sync
BulkAction::on(Product::class)
    ->action('update')
    // Don't use ->sync()
    ->execute();

// OR increase timeout
set_time_limit(300);
```

---

## Performance Limitations

### ~~Limitation 1: Large Dataset Processing~~ ✅ IMPROVED
**Previous Issue:**
- Processing millions of records could be slow
- Memory usage increased with record count

**Status:** ✅ SIGNIFICANTLY IMPROVED
- New `MemoryOptimizer` class for intelligent memory management
- Automatic batch size adjustment based on memory usage
- Configurable memory thresholds and limits
- Garbage collection triggers at 75% memory usage
- Query log clearing to reduce memory footprint

**New Capabilities:**
- Real-time memory monitoring
- Dynamic batch size optimization
- Memory-per-record estimation
- Early warning system for memory limits
- Automatic pause when approaching critical memory levels

**Current Performance:**
- Optimal: < 100,000 records (unchanged)
- Acceptable: 100,000 - 1,000,000 records (improved)
- Large: > 1,000,000 records (now handles better with streaming)

**Configuration:**
```php
// config/action-engine.php
'performance' => [
    'memory_threshold' => 0.8, // Trigger optimization at 80%
    'min_batch_size' => 10,
    'max_batch_size' => 10000,
    'auto_adjust_batch_size' => true,
    'gc_threshold' => 0.75, // Garbage collection at 75%
    'use_cursor' => true, // Database cursor for large datasets
],
```

---

### ~~Limitation 2: Undo Data Storage~~ ⚠️ IMPROVED
**Status:** ⚠️ PARTIALLY IMPROVED
- Enhanced cleanup command with dry-run mode
- Configurable retention periods
- Automatic compression of undo snapshots
- Selective cleanup options

**New Cleanup Features:**
```bash
# Dry run to see what would be deleted
php artisan action-engine:cleanup --dry-run

# Cleanup only specific categories
php artisan action-engine:cleanup --expired --days=30

# Force cleanup without confirmation
php artisan action-engine:cleanup --force
```

---

### Limitation 3: Progress Tracking Overhead
**Issue:**
- Real-time progress tracking adds database writes
- Can slow down execution by 5-10%

**When to Disable:**
```php
// For performance-critical operations
BulkAction::on(Product::class)
    ->action('update')
    // Don't use ->withProgress()
    ->execute();
```

---

## Feature Limitations

### ~~Limitation 1: Export Format Support~~ ✅ IMPROVED
**Available:**
- ✅ CSV Export (built-in)
- ✅ Excel Export (requires `maatwebsite/excel`)
- ✅ PDF Export (requires `barryvdh/laravel-dompdf`)
- ✅ XML Export (NEW - built-in)
- ✅ JSON Export (NEW - built-in)
- ✅ JSON Lines/NDJSON Export (NEW - for large datasets)
- ✅ JSON Streaming (NEW - memory-efficient)

**Status:** ✅ SIGNIFICANTLY IMPROVED
- Added native XML export support with streaming
- Added JSON streaming for large datasets
- Added JSON Lines format for log-style exports
- All new formats support memory-efficient streaming

---

### Limitation 2: Undo Actions
**Cannot Be Undone:**
- Email sending actions
- API calls to external services
- File deletions
- Hard deletes (unless using soft deletes)
- Custom actions that don't support undo

**Can Be Undone:**
- Soft deletes
- Updates (with snapshot)
- Status changes

---

### Limitation 3: Scheduled Actions
**Limitations:**
- Cannot schedule past dates
- Maximum 30 days in future (configurable)
- No recurring schedules
- Single timezone per execution

**Not Supported:**
- Cron-style scheduling
- Recurring patterns
- Complex scheduling rules

---

### Limitation 4: Broadcasting Events
**Limitations:**
- Requires queue workers running
- Requires broadcasting configuration
- May have delays in progress updates
- Limited to supported broadcast drivers (Pusher, Redis, etc.)

---

## Database-Related Issues

### Issue 1: Transaction Conflicts
**Problem:**
- Bulk actions run in batches with separate transactions
- Partial failures possible

**Impact:**
- Some records updated, others not
- Inconsistent state possible

**Mitigation:**
```php
'error_handling' => [
    'continue_on_error' => false, // Stop on first error
    'max_failures_percentage' => 0,
],
```

---

### Issue 2: Foreign Key Constraints
**Error Message:**
```
SQLSTATE[23000]: Integrity constraint violation
```

**When it occurs:**
- Deleting records with foreign key relationships
- Updating keys that break constraints

**Solution:**
```php
// Ensure proper cascade settings in migrations
$table->foreignId('user_id')
    ->constrained()
    ->onDelete('cascade');

// OR handle in code
BulkAction::on(Order::class)
    ->action('delete')
    ->query(function ($q) {
        $q->whereDoesntHave('activeItems');
    })
    ->execute();
```

---

### Issue 3: Database Locks
**Problem:**
- Long-running bulk actions can lock tables
- Other queries blocked

**Symptoms:**
- Slow queries
- Timeout errors
- Deadlocks

**Solutions:**
```php
// Use smaller batch sizes
BulkAction::on(Product::class)
    ->batchSize(50)
    ->execute();

// Process during low-traffic periods
BulkAction::on(Product::class)
    ->scheduleFor('03:00:00')
    ->execute();
```

---

## Queue-Related Issues

### Issue 1: Queue Workers Not Running
**Symptoms:**
- Bulk actions stuck in "pending" status
- No processing happening

**Verification:**
```bash
# Check if workers are running
ps aux | grep "queue:work"

# Check queue status
php artisan queue:failed
```

**Solution:**
```bash
# Start queue worker
php artisan queue:work redis --queue=bulk-actions

# OR use Supervisor (recommended)
```

---

### Issue 2: Failed Jobs
**Error Message:**
```
Job has been attempted too many times or run too long
```

**Common Causes:**
1. Memory limit exceeded
2. Timeout
3. Database connection lost
4. Action errors

**Check Failed Jobs:**
```bash
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

---

### Issue 3: Queue Delay
**Problem:**
- Actions take longer than expected to start
- Queue backlog

**Causes:**
1. Too many jobs in queue
2. Not enough workers
3. Slow job processing

**Solutions:**
```bash
# Add more workers
php artisan queue:work --queue=bulk-actions &

# Process specific queue
php artisan queue:work --queue=high-priority,bulk-actions,default
```

---

## Export Issues

### Issue 1: Missing Export Dependencies
**Error Message:**
```
InvalidActionException: Excel export requires maatwebsite/excel package
```

**When it occurs:**
- Trying to export to Excel without package installed
- Trying to export to PDF without dompdf installed

**Solution:**
```bash
# For Excel
composer require maatwebsite/excel

# For PDF
composer require barryvdh/laravel-dompdf
```

---

### Issue 2: Export Timeout
**Problem:**
- Large exports timing out
- Memory issues with exports

**Solution:**
```php
// Stream exports instead of loading all in memory
BulkAction::on(Product::class)
    ->query(fn($q) => $q->where('status', 'active'))
    ->export('csv', [
        'stream' => true,
        'chunk_size' => 1000,
    ]);
```

---

### Issue 3: Export File Not Found
**Error:**
- Downloaded file is corrupted or empty

**Causes:**
1. Export failed silently
2. File deleted before download
3. Permissions issue

**Debug:**
```php
// Check export status
$execution = BulkActionExecution::where('uuid', $uuid)->first();
if ($execution->export_path && Storage::exists($execution->export_path)) {
    return Storage::download($execution->export_path);
}
```

---

## Frontend Integration Issues

### Issue 1: CSRF Token Mismatch
**Error Message:**
```
419 Page Expired
```

**Solution:**
```javascript
// Ensure CSRF token is included
fetch('/bulk-actions/execute', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
});
```

---

### Issue 2: Progress Not Updating
**Problem:**
- Frontend progress bar stuck
- Events not received

**Checklist:**
1. Broadcasting enabled in config
2. Echo properly configured
3. Queue workers running
4. WebSocket server running (if using Laravel Echo Server)

---

### Issue 3: Livewire Component Missing
**Error:**
```
Component [action-engine.bulk-action-manager] not found
```

**Cause:**
- Livewire stubs not available in package

**Workaround:**
- Manually create Livewire component
- Use vanilla JS/Alpine.js alternative
- Use API endpoints directly

---

## Workarounds & Solutions

### Complete Error Recovery Flow

```php
try {
    $execution = BulkAction::on(Product::class)
        ->action('update')
        ->where('status', 'inactive')
        ->parameters(['status' => 'active'])
        ->withProgress()
        ->withUndo(30)
        ->execute();
        
    return response()->json([
        'success' => true,
        'execution_uuid' => $execution->uuid,
    ]);
    
} catch (\DhruvilNagar\ActionEngine\Exceptions\InvalidActionException $e) {
    // Action not registered
    return response()->json([
        'error' => 'Invalid action',
        'message' => $e->getMessage(),
    ], 400);
    
} catch (\DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException $e) {
    // Rate limit hit
    return response()->json([
        'error' => 'Rate limit exceeded',
        'retry_after' => $e->getRetryAfter(),
    ], 429);
    
} catch (\DhruvilNagar\ActionEngine\Exceptions\UnauthorizedBulkActionException $e) {
    // Permission denied
    return response()->json([
        'error' => 'Unauthorized',
        'message' => $e->getMessage(),
    ], 403);
    
} catch (\Illuminate\Database\QueryException $e) {
    // Database error
    \Log::error('Bulk action database error', [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
    ]);
    
    return response()->json([
        'error' => 'Database error',
        'message' => 'Please try again later',
    ], 500);
    
} catch (\Exception $e) {
    // General error
    \Log::error('Bulk action error', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    return response()->json([
        'error' => 'An error occurred',
        'message' => $e->getMessage(),
    ], 500);
}
```

---

## Testing for Errors

### Test Configuration Issues
```php
// tests/Feature/BulkActionErrorTest.php
public function test_handles_missing_action_error()
{
    $this->expectException(InvalidActionException::class);
    
    BulkAction::on(Product::class)
        ->action('non-existent-action')
        ->execute();
}

public function test_handles_rate_limit()
{
    config(['action-engine.rate_limiting.max_concurrent_actions' => 1]);
    
    // Start first action
    BulkAction::on(Product::class)->action('update')->execute();
    
    // Second should fail
    $this->expectException(RateLimitExceededException::class);
    BulkAction::on(Product::class)->action('update')->execute();
}
```

---

## Monitoring & Debugging

### Enable Debug Mode
```php
// config/action-engine.php
'debug' => [
    'enabled' => env('APP_DEBUG', false),
    'log_queries' => true,
    'log_performance' => true,
],
```

### Logging
```✅ NEW FEATURES & IMPROVEMENTS

### 1. Enhanced Exception Handling
**New Exception Classes:**
- `ActionExecutionException` - Runtime execution errors with context
- `ExportException` - Export-specific failures with helpful messages
- `QueueException` - Queue-related issues with troubleshooting hints

**Features:**
- Record-level error tracking
- Batch-level failure reporting
- Retryable error detection
- Constraint violation handling
- Timeout and memory limit exceptions

**Example:**
```php
try {
    $execution = BulkAction::on(Product::class)
        ->action('update')
        ->execute();
} catch (ActionExecutionException $e) {
    if ($e->isRetryable()) {
        // Retry logic
    }
    $recordId = $e->getRecordId();
    $batchNumber = $e->getBatchNumber();
}
```

### 2. Memory Optimization System
**New `MemoryOptimizer` Class:**
- Real-time memory monitoring
- Automatic batch size adjustment
- Memory usage statistics
- Garbage collection management
- Query log clearing

**Usage:**
```php
$optimizer = new MemoryOptimizer();

// Check memory status
$stats = $optimizer->getStatistics();

// Get recommended batch size
$batchSize = $optimizer->getRecommendedBatchSize(500);

// Optimize before processing
$optimizer->optimizeBeforeBatch();
```

### 3. New Export Drivers
**XmlExportDriver:**
- Streaming support for large datasets
- Configurable element names
- Metadata inclusion
- Proper XML sanitization

**JsonStreamExportDriver:**
- Memory-efficient JSON streaming
- JSON Lines (JSONL) format support
- Newline-delimited JSON (NDJSON)
- Metadata and summary inclusion

**Usage:**
```php
// XML Export
BulkAction::on(Product::class)
    ->export('xml', [
        'root_element' => 'products',
        'row_element' => 'product',
    ]);

// JSON Streaming
BulkAction::on(Product::class)
    ->export('json-stream', [
        'pretty_print' => true,
        'include_metadata' => true,
    ]);
```

### 4. Enhanced Cleanup Command
**New Features:**
- Dry-run mode to preview deletions
- Selective cleanup by category
- Configurable retention periods
- Progress indicators
- Detailed statistics

**Commands:**
```bash
# Preview what would be deleted
php artisan action-engine:cleanup --dry-run

# Clean specific categories
php artisan action-engine:cleanup --expired --days=30
php artisan action-engine:cleanup --failed --days=7
php artisan action-engine:cleanup --audit --days=90

# Force without confirmation
php artisan action-engine:cleanup --force --sync
```

### 5. Monitoring & Observability
**New Monitor Command:**
```bash
# Real-time monitoring
php artisan action-engine:monitor --watch

# Detailed view
php artisan action-engine:monitor --detailed

# Custom refresh interval
php artisan action-engine:monitor --watch --interval=3
```

**New Dashboard Controller:**
- RESTful API endpoints for monitoring
- Real-time metrics
- System health checks
- Performance trends
- User activity tracking

**Endpoints:**
```
GET /bulk-actions/monitoring/overview
GET /bulk-actions/monitoring/metrics
GET /bulk-actions/monitoring/health
GET /bulk-actions/monitoring/action-breakdown
GET /bulk-actions/monitoring/user-activity
GET /bulk-actions/monitoring/performance-trends
```

### 6. Comprehensive Test Suite
**New Tests:**
- Exception handling unit tests (25+ tests)
- Memory optimizer unit tests (30+ tests)
- Error handling integration tests (20+ tests)
- Queue integration tests (25+ tests)

**Coverage:**
- All exception scenarios
- Memory management
- Queue processing
- Error recovery
- Retry logic
- Concurrent operations

### 7. Extended Configuration
**New Performance Settings:**
```php
'performance' => [
    // Memory optimization
    'memory_threshold' => 0.8,
    'min_batch_size' => 10,
    'max_batch_size' => 10000,
    'auto_adjust_batch_size' => true,
    'gc_threshold' => 0.75,
    'clear_query_log' => true,
    
    // Database optimization
    'use_cursor' => true,
    'disable_model_events' => true,
    'select_only_needed_columns' => true,
    
    // Queue optimization
    'queue_timeout' => 3600,
    'queue_memory_limit' => '1G',
    'release_job_on_memory_limit' => true,
],
```

---

## Summary of Improvements

### 🟢 Fully Resolved Issues
1. ✅ Missing Livewire/Filament stubs - All stub files created
2. ✅ Laravel 12+ support - Composer updated for Laravel 10-12
3. ✅ Export format limitations - Added XML, JSON streaming, JSONL
4. ✅ Memory management - New MemoryOptimizer with intelligent handling
5. ✅ Error messages - New exception classes with detailed context
6. ✅ Monitoring - New dashboard and CLI monitoring tools

### 🟡 Significantly Improved
1. ⚠️ Large dataset processing - Better memory management and streaming
2. ⚠️ Cleanup automation - Enhanced command with dry-run and selective cleanup
3. ⚠️ Queue handling - Better error recovery and retry logic

### 🔴 Remaining Known Limitations
1. ❌ Recurring scheduled actions - Still not supported
2. ❌ Some actions cannot be undone (emails, API calls, hard deletes)
3. ❌ Complex scheduling rules - Limited to single future execution
4. ❌ Timezone handling - Single timezone per executionnality)
1. Missing Livewire/Filament stubs during installation
2. Laravel version compatibility (requires 10-11)
3. Queue workers not running (actions won't process)

### 🟡 Warning (May Cause Issues)
1. Memory limit on large datasets
2. Export dependencies not installed
3. Broadcasting not configured (if using real-time features)
4. Foreign key constraints on bulk delete

### 🟢 Minor (Workarounds Available)
1. Progress tracking overhead
2. Undo data storage size
3. Export format limitations
4. Scheduled action limitations

---

## Getting Help

If you encounter an error not listed here:

1. **Check Laravel Logs:** `storage/logs/laravel.log`
2. **Check Queue Failed Jobs:** `php artisan queue:failed`
3. **Enable Debug Mode:** `APP_DEBUG=true`
4. **Check Package Issues:** https://github.com/dhruvilnagar/laravel-action-engine/issues
5. **Community Support:** Laravel forums, Stack Overflow

---

## Future Improvements Needed

Based on testing, these improvements would benefit the package:

1. ✅ Add Laravel 12+ support
2. ✅ Include Livewire/Filament stubs
3. ✅ Better error messages
4. ✅ Memory optimization for large datasets
5. ✅ More export format support
6. ✅ Better documentation on queue configuration
7. ✅ Automated cleanup scheduler
8. ✅ Built-in monitoring dashboard
