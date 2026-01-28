# Architecture Overview

## System Architecture

Laravel Action Engine is built on a modular, event-driven architecture designed for scalability and maintainability.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Client Layer                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐       │
│  │ Livewire │  │   Vue    │  │  React   │  │  Alpine  │       │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘       │
└───────┼─────────────┼─────────────┼─────────────┼──────────────┘
        │             │             │             │
        └─────────────┴─────────────┴─────────────┘
                      │
        ┌─────────────▼─────────────────────────────────────────┐
        │              API/HTTP Layer                            │
        │  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐ │
        │  │ Controllers  │  │  Middleware  │  │   Routes    │ │
        │  └──────────────┘  └──────────────┘  └─────────────┘ │
        └─────────────┬──────────────────────────────────────────┘
                      │
        ┌─────────────▼─────────────────────────────────────────┐
        │              Core Business Logic                       │
        │  ┌───────────────────────────────────────────────┐    │
        │  │         BulkActionBuilder (Fluent API)        │    │
        │  └──────────────────┬────────────────────────────┘    │
        │                     │                                   │
        │  ┌──────────────────▼───────────────────────────┐     │
        │  │          ActionExecutor                      │     │
        │  │  • Validation                                │     │
        │  │  • Authorization (Policies)                  │     │
        │  │  • Batching                                  │     │
        │  │  • Job Dispatching                           │     │
        │  └──────────────────┬───────────────────────────┘     │
        └────────────────────┬┼───────────────────────────────────┘
                             ││
        ┌────────────────────▼▼───────────────────────────────┐
        │              Queue Layer                            │
        │  ┌──────────────────────────────────────────────┐   │
        │  │      ProcessBulkActionBatch (Job)            │   │
        │  │  • Batch Processing                          │   │
        │  │  • Error Handling                            │   │
        │  │  • Progress Tracking                         │   │
        │  └──────────────────┬───────────────────────────┘   │
        └─────────────────────┼───────────────────────────────┘
                              │
        ┌─────────────────────▼───────────────────────────────┐
        │              Support Services                        │
        │  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │
        │  │   Safety    │  │   Progress   │  │    Undo    │ │
        │  │   Manager   │  │   Tracker    │  │   Manager  │ │
        │  └─────────────┘  └──────────────┘  └────────────┘ │
        │  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │
        │  │    Audit    │  │   Export     │  │    Rate    │ │
        │  │   Logger    │  │   Driver     │  │   Limiter  │ │
        │  └─────────────┘  └──────────────┘  └────────────┘ │
        └─────────────────────┬───────────────────────────────┘
                              │
        ┌─────────────────────▼───────────────────────────────┐
        │              Data Layer                              │
        │  ┌───────────────────────────────────────────────┐  │
        │  │              Database Tables                  │  │
        │  │  • bulk_action_executions                     │  │
        │  │  • bulk_action_progress                       │  │
        │  │  • bulk_action_undo                           │  │
        │  │  • bulk_action_audit                          │  │
        │  └───────────────────────────────────────────────┘  │
        └──────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────▼───────────────────────────────┐
        │              Event Layer                             │
        │  • BulkActionStarted                                 │
        │  • BulkActionProgress                                │
        │  • BulkActionCompleted                               │
        │  • BulkActionFailed                                  │
        │  • BulkActionCancelled                               │
        │  • BulkActionUndone                                  │
        └──────────────────────────────────────────────────────┘
```

## Database Schema

### Entity Relationship Diagram

```
┌─────────────────────────────┐
│  bulk_action_executions     │
├─────────────────────────────┤
│ id (PK)                     │
│ action_type                 │
│ model_class                 │
│ status                      │
│ total_records               │
│ processed_records           │
│ failed_records              │
│ is_dry_run                  │
│ created_by                  │
│ created_at                  │
│ completed_at                │
└─────────────┬───────────────┘
              │ 1
              │
              │ N
┌─────────────▼───────────────┐       ┌──────────────────────────┐
│  bulk_action_progress       │       │  bulk_action_undo        │
├─────────────────────────────┤       ├──────────────────────────┤
│ id (PK)                     │       │ id (PK)                  │
│ execution_id (FK)           │       │ execution_id (FK)        │
│ record_id                   │       │ record_id                │
│ status                      │       │ model_type               │
│ error_message               │       │ original_data (JSON)     │
│ processed_at                │       │ created_at               │
└─────────────────────────────┘       └──────────────────────────┘
              
              ┌──────────────────────────┐
              │  bulk_action_audit       │
              ├──────────────────────────┤
              │ id (PK)                  │
              │ execution_id (FK)        │
              │ user_id                  │
              │ action                   │
              │ changes (JSON)           │
              │ ip_address               │
              │ user_agent               │
              │ created_at               │
              └──────────────────────────┘
```

## Component Interaction Flow

### Execution Flow

```
User Request
     │
     ▼
BulkActionBuilder
     │ (builds action configuration)
     ▼
Authorization Check (Policy)
     │
     ├─ Denied ──→ UnauthorizedBulkActionException
     │
     ▼ Allowed
Safety Check (SafetyManager)
     │
     ├─ Requires Confirmation ──→ Return confirmation prompt
     │
     ▼ Confirmed
ActionExecutor
     │
     ├─→ Create BulkActionExecution record
     │
     ├─→ Dispatch BulkActionStarted event
     │
     ├─→ Split records into batches
     │
     ├─→ Dispatch ProcessBulkActionBatch jobs
     │
     └─→ Return execution ID
          │
          ▼
Queue Worker picks up job
     │
     ▼
ProcessBulkActionBatch
     │
     ├─→ Lock records (if enabled)
     │
     ├─→ Process each record
     │     │
     │     ├─→ Store original data (for undo)
     │     ├─→ Execute action
     │     ├─→ Update progress
     │     └─→ Dispatch BulkActionProgress event
     │
     ├─→ Unlock records
     │
     ├─→ Update execution status
     │
     └─→ Dispatch BulkActionCompleted event
          │
          ▼
     Client receives updates (WebSocket/Polling)
          │
          ▼
     Display results to user
```

## Key Design Patterns

### 1. Builder Pattern
**BulkActionBuilder** uses the builder pattern to provide a fluent interface for constructing bulk actions.

```php
BulkAction::on(User::class)
    ->query(fn($q) => $q->where('active', false))
    ->update(['active' => true])
    ->withProgress()
    ->withUndo()
    ->execute();
```

### 2. Strategy Pattern
**Export drivers** implement the Strategy pattern to support different export formats (CSV, Excel, PDF).

### 3. Observer Pattern
**Events** follow the Observer pattern to notify subscribers of action lifecycle events.

### 4. Repository Pattern
**Models** encapsulate data access logic, providing a clean separation from business logic.

### 5. Facade Pattern
**Facades** provide a static interface to core services (BulkAction, ActionRegistry).

## Scalability Considerations

### Horizontal Scaling
- Queue workers can be distributed across multiple servers
- Database read replicas for progress queries
- Redis for distributed locking and caching

### Vertical Scaling
- Configurable batch sizes based on server resources
- Memory-efficient cursor-based pagination
- Lazy loading of related data

### Performance Optimizations
- Database indexing on frequently queried columns
- Eager loading to prevent N+1 queries
- Chunking large datasets to manage memory
- Progress update throttling to reduce I/O

## Security Architecture

### Authorization
- Policy-based access control for actions
- User-specific execution tracking
- IP address and user agent logging

### Data Protection
- Encrypted snapshot storage for undo data
- Secure deletion of expired records
- Audit trail for compliance

### Rate Limiting
- Per-user rate limiting
- Global rate limiting
- Configurable thresholds

## Monitoring & Observability

### Metrics
- Execution success/failure rates
- Average processing time
- Queue depth monitoring
- Memory usage tracking

### Logging
- Structured logging with context
- Error tracking with stack traces
- Audit trail logging
- Performance logging

### Alerting
- Failed execution alerts
- Queue overload alerts
- Memory threshold alerts
- Rate limit alerts

## Configuration Management

All configuration is centralized in `config/action-engine.php`:

```php
[
    'queue' => 'default',
    'batch_size' => 500,
    'enable_undo' => true,
    'undo_ttl' => 168, // hours
    'enable_audit' => true,
    'enable_broadcasting' => false,
    'rate_limit' => 100, // per hour
    'max_retries' => 3,
    'destructive_actions' => ['delete', 'force_delete'],
    'soft_delete_before_hard_delete' => true,
    'require_dry_run_first_time' => true,
]
```

## Extension Points

### Custom Actions
Implement `ActionInterface` to create custom action types:

```php
class CustomAction implements ActionInterface
{
    public function execute($model): void
    {
        // Custom logic
    }
}
```

### Custom Export Drivers
Implement `ExportDriverInterface` for custom export formats:

```php
class CustomExportDriver implements ExportDriverInterface
{
    public function export($data, $filename): string
    {
        // Custom export logic
    }
}
```

### Custom Progress Trackers
Implement `ProgressTrackerInterface` for custom tracking:

```php
class CustomProgressTracker implements ProgressTrackerInterface
{
    public function update($execution, $progress): void
    {
        // Custom progress tracking
    }
}
```

## Best Practices

1. **Always use transactions** for data integrity
2. **Test with production-like data volumes** before deployment
3. **Monitor queue depth** to prevent overload
4. **Set appropriate batch sizes** based on available resources
5. **Enable undo for destructive operations**
6. **Use dry run mode** for testing
7. **Implement proper error handling** in custom actions
8. **Log extensively** for debugging and auditing
9. **Use rate limiting** to prevent abuse
10. **Regular cleanup** of expired undo and audit data
