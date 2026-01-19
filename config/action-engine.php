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

];
