# Laravel Action Engine - Quick Reference

## Installation

```bash
composer require dhruvilnagar/laravel-action-engine
php artisan action-engine:install
```

## Basic Usage

### Simple Delete Action
```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;

BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->sync()
    ->execute();
```

### Update with Parameters
```php
BulkAction::on(Post::class)
    ->action('update')
    ->where('published', false)
    ->withParameters(['status' => 'draft'])
    ->sync()
    ->execute();
```

### With Undo Support
```php
$execution = BulkAction::on(Comment::class)
    ->action('archive')
    ->whereIn('user_id', [1, 2, 3])
    ->withUndo(7) // 7 days to undo
    ->sync()
    ->execute();

// Later, undo if needed
$execution->undo();
```

### Scheduled Execution
```php
BulkAction::on(Subscription::class)
    ->action('cancel')
    ->where('expires_at', '<', now())
    ->scheduleFor(now()->addDays(30), 'America/New_York')
    ->execute();
```

### Dry Run (Preview)
```php
$preview = BulkAction::on(Order::class)
    ->action('delete')
    ->where('status', 'cancelled')
    ->dryRun()
    ->execute();

// Check what would be affected
echo $preview->dry_run_results['count']; // 150 records
```

## Custom Actions

### Register Action
```php
use DhruvilNagar\ActionEngine\Facades\ActionRegistry;

ActionRegistry::register('publish', function ($record, $params) {
    $record->update([
        'published_at' => now(),
        'status' => 'published'
    ]);
}, [
    'label' => 'Publish Posts',
    'supports_undo' => true,
    'confirmation_required' => true,
]);
```

### Class-Based Action
```php
namespace App\Actions;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;

class NotifyUsersAction implements ActionInterface
{
    public function execute($record, array $parameters = []): void
    {
        $record->notify(new CustomNotification($parameters['message']));
    }
    
    public function undo($record, array $originalData): void
    {
        // Mark notification as cancelled
        $record->notifications()->latest()->first()->delete();
    }
}

// Register
ActionRegistry::register('notify', NotifyUsersAction::class);
```

## Progress Tracking

### With Callback
```php
BulkAction::on(User::class)
    ->action('update')
    ->where('active', true)
    ->withParameters(['last_notified' => now()])
    ->onProgress(function ($percentage, $execution) {
        echo "Progress: {$percentage}%\n";
    })
    ->sync()
    ->execute();
```

### Polling API
```javascript
// GET /api/bulk-actions/{uuid}/progress
{
  "success": true,
  "data": {
    "progress_percentage": 45.5,
    "processed_records": 455,
    "total_records": 1000,
    "status": "processing"
  }
}
```

## Configuration Quick Reference

### Environment Variables
```bash
# Batch Processing
ACTION_ENGINE_BATCH_SIZE=500

# Queue
ACTION_ENGINE_QUEUE_CONNECTION=redis
ACTION_ENGINE_QUEUE_NAME=bulk-actions

# Undo
ACTION_ENGINE_UNDO_EXPIRY_DAYS=7

# Rate Limiting
ACTION_ENGINE_RATE_LIMITING_ENABLED=true

# Broadcasting
ACTION_ENGINE_BROADCASTING_ENABLED=true

# Audit
ACTION_ENGINE_AUDIT_ENABLED=true
```

## Common Patterns

### Bulk Update with Conditions
```php
BulkAction::on(Product::class)
    ->action('update')
    ->where('category_id', 5)
    ->whereBetween('price', [10, 50])
    ->withParameters(['discount' => 15])
    ->batchSize(100)
    ->execute();
```

### Archive Old Records
```php
BulkAction::on(Log::class)
    ->action('archive')
    ->where('created_at', '<', now()->subMonths(6))
    ->scheduleFor(now()->addDay(), 'UTC')
    ->execute();
```

### Restore Soft Deleted
```php
BulkAction::on(User::class)
    ->action('restore')
    ->whereIn('id', [1, 2, 3]) // Soft deleted IDs
    ->sync()
    ->execute();
```

### Chain Multiple Actions
```php
BulkAction::on(Order::class)
    ->action('update')
    ->where('status', 'pending')
    ->withParameters(['status' => 'processing'])
    ->then(function ($execution) {
        // Send notifications after update
        BulkAction::on(Order::class)
            ->action('notify')
            ->whereIn('id', $execution->getAffectedIds())
            ->withParameters(['message' => 'Order is being processed'])
            ->sync()
            ->execute();
    })
    ->execute();
```

## Frontend Integration

### Livewire Component
```php
<livewire:bulk-action-manager
    :model="App\Models\User::class"
    :actions="['delete', 'archive', 'update']"
/>
```

### Vue Component
```vue
<bulk-action
    model="users"
    action="delete"
    :filters="{ status: 'inactive' }"
    @completed="handleCompleted"
/>
```

### Blade Progress Bar
```blade
@include('action-engine::blade.progress-bar', [
    'execution' => $execution
])
```

## Console Commands

```bash
# Cleanup expired data
php artisan action-engine:cleanup

# Run cleanup synchronously
php artisan action-engine:cleanup --sync

# List registered actions
php artisan action-engine:list-actions

# Process scheduled actions
php artisan action-engine:process-scheduled
```

## Authorization

### Using Gates
```php
// In AuthServiceProvider
Gate::define('bulk-delete-users', function ($user) {
    return $user->hasRole('admin');
});

// Use gate name
BulkAction::on(User::class)
    ->action('delete')
    ->gate('bulk-delete-users')
    ->execute();
```

### Using Policies
```php
// Automatically checks policy
BulkAction::on(Post::class)
    ->action('delete')
    ->where('author_id', auth()->id())
    ->execute();
```

## Error Handling

```php
try {
    $execution = BulkAction::on(User::class)
        ->action('delete')
        ->where('id', $ids)
        ->sync()
        ->execute();
} catch (\DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException $e) {
    // Rate limit hit
    $retryAfter = $e->getRetryAfter();
} catch (\DhruvilNagar\ActionEngine\Exceptions\UnauthorizedBulkActionException $e) {
    // Permission denied
} catch (\DhruvilNagar\ActionEngine\Exceptions\InvalidActionException $e) {
    // Action not registered
}
```

## Models & Relations

### BulkActionExecution
```php
$execution->progress; // Collection of batch progress
$execution->undoRecords; // Collection of undo snapshots
$execution->user; // User who initiated
$execution->cancel(); // Cancel scheduled action
$execution->undo(); // Undo completed action
```

### Query Scopes
```php
BulkActionExecution::pending()->get();
BulkActionExecution::scheduled()->get();
BulkActionExecution::completed()->get();
BulkActionExecution::forUser($user)->get();
```

## Testing

### In PHPUnit
```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;

public function test_bulk_delete()
{
    $users = User::factory()->count(10)->create();
    
    $execution = BulkAction::on(User::class)
        ->action('delete')
        ->whereIn('id', $users->pluck('id'))
        ->sync()
        ->execute();
    
    $this->assertEquals(10, $execution->processed_records);
    $this->assertDatabaseCount('users', 0);
}
```

## Performance Tips

1. **Use Queues for Large Datasets**
   ```php
   ->queue() // instead of ->sync()
   ```

2. **Adjust Batch Size**
   ```php
   ->batchSize(1000) // Larger batches = fewer jobs
   ```

3. **Disable Undo for Large Operations**
   ```php
   // Don't use withUndo() for huge datasets
   ```

4. **Use whereIn for Specific IDs**
   ```php
   ->whereIn('id', $ids) // More efficient than complex queries
   ```

5. **Schedule Heavy Operations**
   ```php
   ->scheduleFor(now()->addHours(2)) // Off-peak hours
   ```

## Troubleshooting

### Progress Not Updating
- Check broadcasting configuration
- Verify WebSocket connection
- Ensure queue workers are running

### Undo Not Working
- Verify `withUndo()` was called
- Check undo hasn't expired
- Ensure execution completed successfully

### Actions Not Executing
- Check queue workers: `php artisan queue:work`
- Verify action is registered
- Check authorization policies

### Rate Limit Issues
- Adjust limits in config
- Wait for cooldown period
- Use `--sync` for cleanup command

## Support

- **Documentation**: [Link to full docs]
- **Issues**: https://github.com/dhruvilnagar/laravel-action-engine/issues
- **Discussions**: https://github.com/dhruvilnagar/laravel-action-engine/discussions

---

**Version**: 1.0.0  
**Updated**: January 19, 2026
