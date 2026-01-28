<?php

/**
 * Laravel Action Engine Configuration
 * 
 * This file contains all configuration options for the Laravel Action Engine package.
 * 
 * The package provides a powerful system for executing, tracking, and managing
 * bulk operations on Eloquent models with features including:
 * - Batch processing with progress tracking
 * - Undo/redo functionality
 * - Scheduled execution
 * - Rate limiting and authorization
 * - Real-time broadcasting
 * - Comprehensive audit logging
 * 
 * Environment variables are available for commonly changed settings.
 * See .env.example for a complete list of available environment variables.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Batch Size
    |--------------------------------------------------------------------------
    |
    | The default number of records to process in each queued batch.
    | Can be overridden per action using the batchSize() method.
    |
    */
    'batch_size' => env('ACTION_ENGINE_BATCH_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and queue name for bulk action jobs.
    |
    */
    'queue' => [
        'connection' => env('ACTION_ENGINE_QUEUE_CONNECTION', null), // null = default connection
        'name' => env('ACTION_ENGINE_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for the package routes.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => env('ACTION_ENGINE_ROUTE_PREFIX', 'bulk-actions'),
        'middleware' => [
            'api' => ['api', 'auth:sanctum'],
            'web' => ['web', 'auth'],
        ],
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Undo Configuration
    |--------------------------------------------------------------------------
    |
    | Configure undo functionality including default expiry and snapshot storage.
    |
    */
    'undo' => [
        'enabled' => true,
        'default_expiry_days' => env('ACTION_ENGINE_UNDO_EXPIRY_DAYS', 7),
        'max_expiry_days' => 90,
        'snapshot_fields' => true, // Store full record snapshots for undo
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure real-time progress updates via WebSocket broadcasting.
    |
    */
    'broadcasting' => [
        'enabled' => env('ACTION_ENGINE_BROADCASTING_ENABLED', false),
        'channel_prefix' => 'bulk-action',
        'driver' => env('BROADCAST_DRIVER', 'pusher'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Progress Tracking Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how progress is tracked and reported.
    |
    */
    'progress' => [
        'update_frequency' => 10, // Update progress every N records
        'broadcast_throttle_ms' => 500, // Minimum ms between broadcasts
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audit logging for bulk actions.
    |
    */
    'audit' => [
        'enabled' => env('ACTION_ENGINE_AUDIT_ENABLED', true),
        'log_parameters' => true, // Log action parameters
        'log_affected_ids' => true, // Log affected record IDs
        'retention_days' => 90, // Days to keep audit logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to prevent system overload.
    |
    */
    'rate_limiting' => [
        'enabled' => env('ACTION_ENGINE_RATE_LIMITING_ENABLED', true),
        'max_concurrent_actions' => 5, // Max concurrent bulk actions per user
        'max_records_per_action' => 100000, // Max records in a single action
        'cooldown_seconds' => 60, // Cooldown between large actions
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of old data.
    |
    */
    'cleanup' => [
        'auto_cleanup' => true,
        'execution_retention_days' => 30, // Days to keep execution records
        'progress_retention_days' => 7, // Days to keep progress records
        'run_cleanup_on_schedule' => true, // Run cleanup via scheduler
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure scheduled bulk actions.
    |
    */
    'scheduling' => [
        'enabled' => true,
        'max_scheduled_days_ahead' => 365, // Max days in future to schedule
        'check_interval_minutes' => 1, // How often to check for due actions
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure export functionality for bulk actions.
    |
    */
    'export' => [
        'disk' => env('ACTION_ENGINE_EXPORT_DISK', 'local'),
        'directory' => 'bulk-action-exports',
        'max_export_records' => 100000,
        'chunk_size' => 1000,
        'formats' => ['csv', 'xlsx', 'pdf'],
        'cleanup_after_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dry Run Configuration
    |--------------------------------------------------------------------------
    |
    | Configure dry run mode settings.
    |
    */
    'dry_run' => [
        'preview_limit' => 100, // Max records to show in preview
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */
    'tables' => [
        'executions' => 'bulk_action_executions',
        'progress' => 'bulk_action_progress',
        'undo' => 'bulk_action_undo',
        'audit' => 'bulk_action_audit',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the models used by the package. You can extend the default
    | models and specify your custom classes here.
    |
    */
    'models' => [
        'execution' => \DhruvilNagar\ActionEngine\Models\BulkActionExecution::class,
        'progress' => \DhruvilNagar\ActionEngine\Models\BulkActionProgress::class,
        'undo' => \DhruvilNagar\ActionEngine\Models\BulkActionUndo::class,
        'audit' => \DhruvilNagar\ActionEngine\Models\BulkActionAudit::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authorization for bulk actions.
    |
    */
    'authorization' => [
        'enabled' => true,
        'use_policies' => true, // Use Laravel policies for authorization
        'default_gate' => null, // Default gate ability to check
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Features Configuration
    |--------------------------------------------------------------------------
    |
    | Configure safety features for destructive operations.
    |
    */
    'safety' => [
        'destructive_actions' => ['delete', 'force_delete', 'truncate'],
        'require_confirmation' => true,
        'soft_delete_before_hard_delete' => true,
        'enable_record_locking' => true,
        'rollback_on_partial_failure' => true,
        'require_dry_run_first_time' => true,
        'confirmation_threshold' => 1000, // Require typing confirmation for > 1000 records
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Observability Configuration
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and observability integrations.
    |
    */
    'monitoring' => [
        'enabled' => env('ACTION_ENGINE_MONITORING_ENABLED', true),
        'driver' => env('ACTION_ENGINE_MONITORING_DRIVER', 'log'),
        
        // Telescope integration
        'telescope' => [
            'enabled' => class_exists(\Laravel\Telescope\Telescope::class),
        ],
        
        // Sentry error tracking
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', false),
        ],
        
        // Bugsnag error tracking
        'bugsnag' => [
            'enabled' => env('BUGSNAG_ENABLED', false),
        ],
        
        // Datadog APM
        'datadog' => [
            'enabled' => env('DATADOG_ENABLED', false),
            'host' => env('DATADOG_HOST', '127.0.0.1'),
            'port' => env('DATADOG_PORT', 8125),
        ],
        
        // Prometheus metrics
        'prometheus' => [
            'enabled' => env('PROMETHEUS_ENABLED', false),
        ],
        
        // Alert thresholds
        'alerts' => [
            'queue_threshold' => 100,
            'failed_jobs_threshold' => 10,
            'stuck_execution_hours' => 1,
            'disk_usage_percentage' => 90,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Configuration
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings.
    |
    */
    'performance' => [
        'enable_compression' => true, // Compress undo snapshots
        'enable_deduplication' => true, // Only store changed fields
        'cache_progress' => true, // Cache progress data
        'cache_ttl' => 300, // Cache TTL in seconds
        'max_retries' => 3, // Maximum retry attempts for failed batches
        
        // Memory optimization
        'memory_threshold' => 0.8, // Trigger memory optimization at 80% usage
        'min_batch_size' => 10, // Minimum batch size when memory is constrained
        'max_batch_size' => 10000, // Maximum batch size
        'auto_adjust_batch_size' => true, // Automatically adjust based on memory
        'chunk_size' => 100, // Database query chunk size
        'memory_limit' => env('ACTION_ENGINE_MEMORY_LIMIT', '512M'), // PHP memory limit
        'gc_threshold' => 0.75, // Trigger garbage collection at 75%
        'clear_query_log' => true, // Clear query log to save memory
        
        // Database optimization
        'use_cursor' => true, // Use database cursor for large datasets
        'disable_model_events' => true, // Disable model events during bulk operations
        'disable_timestamps' => false, // Optionally disable timestamps
        'select_only_needed_columns' => true, // Only select required columns
        
        // Queue optimization
        'queue_timeout' => 3600, // Job timeout in seconds
        'queue_memory_limit' => '1G', // Memory limit for queued jobs
        'release_job_on_memory_limit' => true, // Release job if approaching memory limit
    ],

];
