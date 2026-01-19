# Advanced Usage Guide

## Table of Contents

1. [Custom Actions](#custom-actions)
2. [Action Chaining](#action-chaining)
3. [Scheduled Actions](#scheduled-actions)
4. [Authorization](#authorization)
5. [Progress Callbacks](#progress-callbacks)
6. [Dry Run Mode](#dry-run-mode)
7. [Export Integration](#export-integration)
8. [Rate Limiting](#rate-limiting)

## Custom Actions

### Registering Custom Actions

You can register custom actions in your `AppServiceProvider`:

```php
use DhruvilNagar\ActionEngine\Facades\ActionRegistry;

public function boot(): void
{
    ActionRegistry::register('send_notification', function ($record, $params) {
        $record->notify(new CustomNotification($params['message']));
        return true;
    }, [
        'label' => 'Send Notification',
        'supports_undo' => false,
        'confirmation_required' => true,
        'confirmation_message' => 'Send notification to selected users?',
        'parameters' => [
            'message' => ['type' => 'string', 'required' => true],
        ],
    ]);
}
```

### Using Class-Based Actions

For more complex actions, create a dedicated action class:

```php
namespace App\Actions;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use Illuminate\Database\Eloquent\Model;

class SuspendUserAction implements ActionInterface
{
    public function execute(Model $record, array $parameters = []): bool
    {
        $record->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $parameters['reason'] ?? null,
        ]);

        // Send notification
        $record->notify(new AccountSuspendedNotification());

        return true;
    }

    public function getName(): string
    {
        return 'suspend_user';
    }

    public function getLabel(): string
    {
        return 'Suspend User';
    }

    public function supportsUndo(): bool
    {
        return true;
    }

    public function getUndoType(): ?string
    {
        return 'update';
    }

    public function validateParameters(array $parameters): array
    {
        return validator($parameters, [
            'reason' => 'required|string|max:500',
        ])->validate();
    }

    public function getUndoFields(): array
    {
        return ['status', 'suspended_at', 'suspension_reason'];
    }
}
```

Register it in your service provider:

```php
ActionRegistry::register('suspend_user', SuspendUserAction::class, [
    'label' => 'Suspend User',
    'supports_undo' => true,
    'confirmation_required' => true,
]);
```

## Action Chaining

Execute multiple actions in sequence:

```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;

// First, update status
$execution1 = BulkAction::on(User::class)
    ->action('update')
    ->where('last_login_at', '<', now()->subYears(1))
    ->with(['data' => ['status' => 'inactive']])
    ->execute();

// Wait for completion
while ($execution1->status === 'processing') {
    sleep(2);
    $execution1->refresh();
}

// Then archive inactive users
if ($execution1->status === 'completed') {
    $execution2 = BulkAction::on(User::class)
        ->action('archive')
        ->where('status', 'inactive')
        ->with(['reason' => 'Automatic cleanup'])
        ->withUndo(days: 30)
        ->execute();
}
```

### Using Callbacks for Chaining

```php
$execution = BulkAction::on(User::class)
    ->action('update')
    ->where('subscription_ends_at', '<', now())
    ->with(['data' => ['plan' => 'free']])
    ->onComplete(function ($execution) {
        // Chain another action after completion
        BulkAction::on(User::class)
            ->ids($execution->getAffectedIds())
            ->action('send_notification')
            ->with(['message' => 'Your subscription has expired'])
            ->execute();
    })
    ->execute();
```

## Scheduled Actions

### Basic Scheduling

```php
use Carbon\Carbon;

$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('trial_ends_at', '<', now())
    ->where('has_payment_method', false)
    ->scheduleFor(Carbon::tomorrow()->hour(2)) // 2 AM tomorrow
    ->execute();
```

### Recurring Actions

Set up recurring bulk actions in `routes/console.php`:

```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;

Schedule::call(function () {
    BulkAction::on(User::class)
        ->action('archive')
        ->where('last_login_at', '<', now()->subMonths(6))
        ->with(['reason' => 'Automatic 6-month inactivity archive'])
        ->withUndo(days: 30)
        ->execute();
})->weekly()->mondays()->at('02:00');
```

### Managing Scheduled Actions

```php
// List all pending scheduled actions
$scheduled = BulkActionExecution::query()
    ->where('status', 'pending')
    ->whereNotNull('scheduled_for')
    ->where('scheduled_for', '>', now())
    ->get();

// Cancel a scheduled action
$execution = BulkActionExecution::where('uuid', $uuid)->first();
$execution->cancel();
```

## Authorization

### Policy-Based Authorization

Create a policy:

```php
namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function bulkDelete(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function bulkArchive(User $user): bool
    {
        return $user->hasPermission('archive_users');
    }
}
```

Configure in `config/action-engine.php`:

```php
'authorization' => [
    'enabled' => true,
    'policy_method_prefix' => 'bulk', // e.g., bulkDelete, bulkUpdate
],
```

### Custom Authorization

```php
$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->authorize(function ($user, $modelClass, $action) {
        // Custom authorization logic
        return $user->can('delete_users') && $user->department === 'IT';
    })
    ->execute();
```

### Per-Record Authorization

```php
ActionRegistry::register('sensitive_update', function ($record, $params) use ($user) {
    // Check if user can update this specific record
    if (!$user->can('update', $record)) {
        throw new UnauthorizedException("Not authorized to update record {$record->id}");
    }

    $record->update($params['data']);
    return true;
});
```

## Progress Callbacks

### Track Progress

```php
$execution = BulkAction::on(User::class)
    ->action('update')
    ->where('status', 'active')
    ->with(['data' => ['verified' => true]])
    ->onProgress(function ($progress) {
        Log::info("Progress: {$progress['percentage']}%", [
            'processed' => $progress['processed'],
            'total' => $progress['total'],
            'failed' => $progress['failed'],
        ]);
    })
    ->onComplete(function ($execution) {
        Log::info("Completed bulk update", [
            'total' => $execution->total_records,
            'success_rate' => $execution->success_rate,
        ]);
        
        // Notify admin
        User::whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->each(fn($admin) => $admin->notify(new BulkActionCompletedNotification($execution)));
    })
    ->onFailure(function ($execution, $error) {
        Log::error("Bulk action failed", [
            'uuid' => $execution->uuid,
            'error' => $error->getMessage(),
        ]);
        
        // Alert via Slack, email, etc.
    })
    ->execute();
```

### Real-Time Updates

Listen for progress events:

```php
// In a controller or listener
Event::listen(BulkActionProgress::class, function ($event) {
    broadcast(new BulkActionProgressUpdate($event->execution));
});
```

In your frontend:

```javascript
// With Laravel Echo
Echo.channel(`bulk-action.${executionUuid}`)
    .listen('BulkActionProgressUpdate', (e) => {
        updateProgressBar(e.percentage);
    });
```

## Dry Run Mode

### Preview Changes

```php
$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('last_login_at', '<', now()->subYear())
    ->dryRun()
    ->execute();

// Get results
$results = $execution->dry_run_results;
echo "Would affect: {$results['affected_count']} records\n";
echo "Sample records:\n";
foreach ($results['sample_records'] as $record) {
    echo "- {$record['name']} ({$record['email']})\n";
}
```

### Preview Count

```php
$count = BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->where('created_at', '<', now()->subYears(2))
    ->count();

echo "This will affect {$count} records";
```

### Preview Sample

```php
$sample = BulkAction::on(User::class)
    ->action('archive')
    ->where('subscription_status', 'cancelled')
    ->preview(10); // Get 10 sample records

foreach ($sample as $user) {
    echo "Would archive: {$user->name} - {$user->email}\n";
}
```

## Export Integration

### Basic Export

```php
$execution = BulkAction::on(User::class)
    ->action('export')
    ->where('created_at', '>=', now()->startOfYear())
    ->with([
        'format' => 'csv',
        'filename' => 'users_2024',
        'columns' => ['id', 'name', 'email', 'created_at'],
    ])
    ->execute();
```

### Custom Export Driver

```php
use DhruvilNagar\ActionEngine\Contracts\ExportDriverInterface;
use Illuminate\Support\Collection;

class JsonExportDriver implements ExportDriverInterface
{
    public function generate(Collection $data, array $options = []): string
    {
        return json_encode($data->toArray(), JSON_PRETTY_PRINT);
    }

    public function download(string $content, string $filename)
    {
        return response()->streamDownload(
            fn() => echo $content,
            $filename,
            ['Content-Type' => 'application/json']
        );
    }

    public function stream(callable $dataCallback, string $filename, array $options = [])
    {
        // Implementation for streaming large datasets
    }
}
```

Register the driver:

```php
app(ExportManager::class)->register('json', new JsonExportDriver());
```

### Streaming Large Exports

```php
use DhruvilNagar\ActionEngine\Support\ExportManager;

$exportManager = app(ExportManager::class);

return $exportManager->stream(
    function ($callback) {
        User::query()
            ->where('created_at', '>=', now()->startOfYear())
            ->chunk(1000, function ($users) use ($callback) {
                $callback($users->toArray());
            });
    },
    'csv',
    'large_user_export.csv',
    ['chunk_size' => 1000]
);
```

## Rate Limiting

### Global Rate Limiting

Configure in `config/action-engine.php`:

```php
'rate_limiting' => [
    'enabled' => true,
    'max_executions_per_user' => 10,
    'decay_minutes' => 60,
    'max_concurrent_executions' => 5,
],
```

### Per-Action Rate Limiting

```php
ActionRegistry::register('expensive_action', function ($record, $params) {
    // ...expensive operation
}, [
    'rate_limit' => [
        'max_attempts' => 2,
        'decay_minutes' => 120, // 2 per 2 hours
    ],
]);
```

### Custom Rate Limiting

```php
use DhruvilNagar\ActionEngine\Support\RateLimiter;

$rateLimiter = app(RateLimiter::class);

if ($rateLimiter->tooManyAttempts(auth()->id(), 'bulk_action', 5)) {
    $seconds = $rateLimiter->availableIn(auth()->id(), 'bulk_action');
    throw new RateLimitExceededException(
        "Too many bulk actions. Try again in {$seconds} seconds."
    );
}

$rateLimiter->hit(auth()->id(), 'bulk_action', 60);
```

### Throttling Queue Jobs

```php
'queue' => [
    'connection' => 'redis',
    'name' => 'bulk-actions',
    'throttle' => [
        'enabled' => true,
        'max_jobs_per_minute' => 60,
    ],
],
```

## Best Practices

### 1. Use Appropriate Batch Sizes

```php
// For fast operations (updates)
->batchSize(1000)

// For slow operations (API calls, complex calculations)
->batchSize(100)

// For very slow operations
->batchSize(10)
```

### 2. Always Use Undo for Destructive Actions

```php
BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'spam')
    ->withUndo(days: 30) // Keep undo data for 30 days
    ->execute();
```

### 3. Schedule Heavy Operations

```php
// Don't do this during business hours
BulkAction::on(Order::class)
    ->action('recalculate_totals')
    ->where('created_at', '>=', now()->subMonth())
    ->scheduleFor(Carbon::tomorrow()->hour(2))
    ->execute();
```

### 4. Monitor and Log

```php
BulkAction::on(User::class)
    ->action('sensitive_operation')
    ->where('role', 'admin')
    ->onProgress(fn($p) => Log::info("Progress: {$p['percentage']}%"))
    ->onComplete(fn($e) => Log::info("Completed", ['uuid' => $e->uuid]))
    ->onFailure(fn($e, $err) => Log::error("Failed", ['error' => $err]))
    ->execute();
```

### 5. Use Queue for Large Datasets

```php
// Automatically queued (default)
BulkAction::on(User::class)->action('update')->ids($largeArrayOfIds)->execute();

// Only use sync for small operations
BulkAction::on(User::class)->action('update')->ids([1, 2, 3])->sync()->execute();
```
