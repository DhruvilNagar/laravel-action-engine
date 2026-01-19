# Laravel Action Engine - Complete Documentation

**Version:** 1.0.0  
**Last Updated:** January 19, 2026

---

## Table of Contents

1. [Introduction](#introduction)
2. [Features](#features)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Quick Start](#quick-start)
6. [Configuration](#configuration)
7. [Core Concepts](#core-concepts)
8. [Built-in Actions](#built-in-actions)
9. [Frontend Integration](#frontend-integration)
10. [Custom Actions](#custom-actions)
11. [Advanced Usage](#advanced-usage)
12. [API Reference](#api-reference)
13. [Best Practices](#best-practices)
14. [Troubleshooting](#troubleshooting)
15. [Contributing](#contributing)

---

## Introduction

Laravel Action Engine is a powerful, framework-agnostic Laravel package designed to simplify and streamline bulk operations on your Eloquent models. Whether you need to delete thousands of inactive users, update subscription statuses, archive old records, or export data, this package provides an elegant, type-safe API with enterprise-grade features.

### What Makes It Special?

- **Framework Agnostic**: Works with any Laravel frontend stack (Livewire, Vue, React, Blade, Filament, Alpine.js)
- **Production Ready**: Built with enterprise features like rate limiting, audit trails, and comprehensive error handling
- **Developer Friendly**: Fluent API with method chaining, full type hints, and extensive documentation
- **Battle Tested**: 98% test coverage with comprehensive unit and feature tests

### Use Cases

- **User Management**: Bulk delete inactive users, suspend accounts, update roles
- **Data Cleanup**: Archive old records, remove expired data, merge duplicates
- **Batch Updates**: Update pricing, change statuses, assign categories
- **Data Export**: Export filtered data to CSV, Excel, or PDF
- **Scheduled Operations**: Defer bulk actions to off-peak hours
- **Audit & Compliance**: Track all bulk operations with comprehensive audit trails

---

## Features

### Core Features

✅ **Fluent API** - Intuitive, chainable methods for building bulk actions  
✅ **Queue Integration** - Automatic batching and background processing for large datasets  
✅ **Progress Tracking** - Real-time progress updates via polling or WebSocket  
✅ **Undo Functionality** - Time-limited undo with full record snapshots  
✅ **Scheduled Actions** - Defer execution to specific times with timezone support  
✅ **Dry Run Mode** - Preview affected records before executing  
✅ **Action Chaining** - Execute multiple actions sequentially  
✅ **Audit Trail** - Complete history of all bulk operations  
✅ **Rate Limiting** - Prevent system overload with configurable throttling  
✅ **Export Integration** - Export results to CSV, Excel, or PDF  
✅ **Authorization** - Policy-based access control  

### Frontend Integrations

✅ **Livewire Components** - Ready-to-use Blade components with progress tracking  
✅ **Vue 3 Composables** - Reactive hooks for Vue applications  
✅ **React Hooks** - Custom hooks with TypeScript support  
✅ **Alpine.js Components** - Lightweight components for Alpine.js  
✅ **Filament Actions** - 5 pre-built bulk actions for Filament Admin  
✅ **Blade Templates** - Traditional Blade views with modals and progress bars  

### Enterprise Features

✅ **Comprehensive Audit Logging** - Track who did what and when  
✅ **Rate Limiting** - Prevent abuse and system overload  
✅ **Error Recovery** - Automatic retry with exponential backoff  
✅ **Transaction Safety** - Atomic operations with rollback support  
✅ **Memory Efficient** - Chunked processing for millions of records  
✅ **Monitoring Ready** - Events for integration with monitoring tools  

---

## Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.x or 11.x
- **Database**: MySQL 5.7+, PostgreSQL 10+, SQLite 3.8+, or SQL Server 2017+
- **Queue Driver**: Any (database, Redis, Beanstalkd, SQS, etc.)

### Optional Dependencies

- **Livewire**: 3.0+ (for Livewire components)
- **Filament**: 3.0+ (for Filament integration)
- **Pusher** or **Laravel Echo**: For real-time WebSocket updates
- **maatwebsite/excel**: 3.1+ (for Excel export)
- **barryvdh/laravel-dompdf**: 2.0+ (for PDF export)

---

## Installation

### Step 1: Install via Composer

```bash
composer require dhruvilnagar/laravel-action-engine
```

The package will automatically register itself via Laravel's package auto-discovery.

### Step 2: Run the Interactive Installer

```bash
php artisan action-engine:install
```

The installer will guide you through:

1. **Frontend Stack Selection** - Choose which frontend stacks you're using:
   - Livewire
   - Vue 3
   - React
   - Blade
   - Filament
   - Alpine.js

2. **Broadcasting Configuration** - Optionally enable real-time progress updates:
   - Choose to enable WebSocket broadcasting
   - Select your broadcast driver (Pusher, Redis, etc.)

3. **Asset Publishing** - The installer will publish:
   - Configuration file (`config/action-engine.php`)
   - Database migrations
   - Frontend components for selected stacks

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates four tables:
- `bulk_action_executions` - Tracks all bulk action executions
- `bulk_action_progress` - Stores progress information
- `bulk_action_undo` - Stores undo snapshots
- `bulk_action_audit` - Comprehensive audit logs

### Step 4: Configure Queue (Recommended)

For optimal performance with large datasets, configure a queue driver:

```env
# .env
QUEUE_CONNECTION=redis
ACTION_ENGINE_QUEUE_NAME=bulk-actions
```

Start your queue worker:

```bash
php artisan queue:work --queue=bulk-actions
```

### Manual Installation (Alternative)

If you prefer manual setup:

```bash
# Publish configuration
php artisan vendor:publish --tag=action-engine-config

# Publish migrations
php artisan vendor:publish --tag=action-engine-migrations

# Publish frontend assets (optional)
php artisan vendor:publish --tag=action-engine-vue
php artisan vendor:publish --tag=action-engine-react
php artisan vendor:publish --tag=action-engine-livewire
php artisan vendor:publish --tag=action-engine-filament
php artisan vendor:publish --tag=action-engine-alpine
php artisan vendor:publish --tag=action-engine-blade

# Run migrations
php artisan migrate
```

---

## Quick Start

### Your First Bulk Action

Let's delete all inactive users who haven't logged in for 6 months:

```php
use DhruvilNagar\ActionEngine\Facades\BulkAction;
use App\Models\User;

$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->where('last_login_at', '<', now()->subMonths(6))
    ->withUndo(days: 30) // Allow undo for 30 days
    ->execute();

// Get execution details
echo "UUID: {$execution->uuid}\n";
echo "Status: {$execution->status}\n";
echo "Total Records: {$execution->total_records}\n";
echo "Progress: {$execution->progress_percentage}%\n";
```

### Bulk Update Example

Update all trial users to premium:

```php
$execution = BulkAction::on(User::class)
    ->action('update')
    ->where('plan', 'trial')
    ->where('trial_ends_at', '<', now())
    ->with(['data' => ['plan' => 'premium', 'upgraded_at' => now()]])
    ->execute();
```

### Using Specific IDs

Execute action on specific records:

```php
$execution = BulkAction::on(User::class)
    ->action('archive')
    ->ids([1, 2, 3, 4, 5])
    ->with(['reason' => 'Account cleanup'])
    ->withUndo()
    ->execute();
```

### Preview Before Executing (Dry Run)

```php
$preview = BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->dryRun()
    ->execute();

// Check what would be affected
echo "Would affect {$preview->dry_run_results['count']} records\n";
print_r($preview->dry_run_results['sample']); // First 100 records
```

### Checking Progress

```php
// Poll for progress
while ($execution->isInProgress()) {
    $execution->refresh();
    echo "Progress: {$execution->progress_percentage}%\n";
    echo "Processed: {$execution->processed_records}/{$execution->total_records}\n";
    sleep(2);
}

// Check final status
if ($execution->isCompleted()) {
    echo "Success! Processed {$execution->processed_records} records.\n";
} elseif ($execution->isFailed()) {
    echo "Failed: {$execution->error_message}\n";
}
```

### Undo an Action

```php
// Check if can be undone
if ($execution->canUndo()) {
    $execution->undo();
    echo "Action undone successfully!\n";
} else {
    echo "Cannot undo: {$execution->getUndoStatus()}\n";
}
```

### Schedule for Later

```php
use Carbon\Carbon;

$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('trial_ends_at', '<', now())
    ->scheduleFor(Carbon::tomorrow()->hour(2), 'America/New_York')
    ->execute();

echo "Scheduled for execution at 2 AM EST tomorrow\n";
```

---

## Configuration

The package configuration file is located at `config/action-engine.php`. Here's a comprehensive guide to all available options:

### Batch Processing

```php
'batch_size' => env('ACTION_ENGINE_BATCH_SIZE', 500),
```

Controls how many records are processed in each queue job. Smaller batches = more frequent progress updates but more overhead. Larger batches = better performance but less frequent updates.

**Recommended values:**
- Small datasets (< 10,000 records): 100-500
- Medium datasets (10,000 - 100,000): 500-1,000
- Large datasets (> 100,000): 1,000-5,000

### Queue Configuration

```php
'queue' => [
    'connection' => env('ACTION_ENGINE_QUEUE_CONNECTION', null), // null = default
    'name' => env('ACTION_ENGINE_QUEUE_NAME', 'default'),
],
```

Configure which queue connection and queue name to use for bulk action jobs.

**Environment variables:**
```env
ACTION_ENGINE_QUEUE_CONNECTION=redis
ACTION_ENGINE_QUEUE_NAME=bulk-actions
```

### Route Configuration

```php
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
```

- **enabled**: Set to `false` to disable automatic route registration
- **prefix**: URL prefix for all routes (e.g., `/api/bulk-actions`)
- **middleware**: Middleware groups for API and web routes
- **rate_limit**: Protect endpoints from abuse

### Undo Configuration

```php
'undo' => [
    'enabled' => true,
    'default_expiry_days' => env('ACTION_ENGINE_UNDO_EXPIRY_DAYS', 7),
    'max_expiry_days' => 90,
    'snapshot_fields' => true, // Store full record snapshots
],
```

- **enabled**: Enable/disable undo functionality globally
- **default_expiry_days**: Default undo window (can be overridden per action)
- **max_expiry_days**: Maximum allowed undo period
- **snapshot_fields**: Store complete record state for accurate restoration

### Broadcasting (Real-time Updates)

```php
'broadcasting' => [
    'enabled' => env('ACTION_ENGINE_BROADCASTING_ENABLED', false),
    'channel_prefix' => 'bulk-action',
    'driver' => env('BROADCAST_DRIVER', 'pusher'),
],
```

Enable real-time progress updates via WebSocket:

```env
ACTION_ENGINE_BROADCASTING_ENABLED=true
BROADCAST_DRIVER=pusher
```

### Progress Tracking

```php
'progress' => [
    'update_frequency' => 10, // Update every N records
    'broadcast_throttle_ms' => 500, // Min ms between broadcasts
],
```

Control how often progress is tracked and broadcast to reduce overhead.

### Audit Trail

```php
'audit' => [
    'enabled' => env('ACTION_ENGINE_AUDIT_ENABLED', true),
    'log_parameters' => true,
    'log_affected_ids' => true,
    'retention_days' => 90,
],
```

Comprehensive audit logging for compliance and debugging.

### Rate Limiting

```php
'rate_limiting' => [
    'enabled' => env('ACTION_ENGINE_RATE_LIMITING_ENABLED', true),
    'max_concurrent_actions' => 5,
    'max_records_per_action' => 100000,
    'cooldown_seconds' => 60,
],
```

Prevent system overload and abuse:
- **max_concurrent_actions**: Max simultaneous actions per user
- **max_records_per_action**: Hard limit on action size
- **cooldown_seconds**: Required wait time between large actions

### Cleanup Configuration

```php
'cleanup' => [
    'auto_cleanup' => true,
    'execution_retention_days' => 30,
    'progress_retention_days' => 7,
    'run_cleanup_on_schedule' => true,
],
```

Automatic cleanup of old data to keep database lean.

### Export Configuration

```php
'export' => [
    'disk' => env('ACTION_ENGINE_EXPORT_DISK', 'local'),
    'directory' => 'bulk-action-exports',
    'max_export_records' => 100000,
    'chunk_size' => 1000,
    'formats' => ['csv', 'xlsx', 'pdf'],
    'cleanup_after_days' => 7,
],
```

Configure export functionality and storage.

### Database Tables

```php
'tables' => [
    'executions' => 'bulk_action_executions',
    'progress' => 'bulk_action_progress',
    'undo' => 'bulk_action_undo',
    'audit' => 'bulk_action_audit',
],
```

Customize table names if needed for your database schema.

### Authorization

```php
'authorization' => [
    'enabled' => true,
    'use_policies' => true,
    'default_gate' => null,
],
```

Enable policy-based authorization for bulk actions.

---

## Core Concepts

### Execution Lifecycle

Every bulk action goes through these stages:

```
pending → processing → completed/failed/cancelled
```

1. **Pending**: Action is queued, waiting for execution
2. **Processing**: Batches are being processed
3. **Completed**: All batches processed successfully
4. **Failed**: Action encountered errors
5. **Cancelled**: User manually cancelled the action

### Batch Processing

Large datasets are automatically split into batches:

```php
// 10,000 records with batch size 500 = 20 batches
BulkAction::on(User::class)
    ->action('update')
    ->where('active', true)
    ->batchSize(500)  // Process 500 records per batch
    ->execute();
```

**Benefits:**
- Memory efficient (only loads one batch at a time)
- Resumable (failures don't affect completed batches)
- Progress tracking (updated after each batch)
- Queue friendly (many small jobs vs one huge job)

### Synchronous vs Asynchronous

**Asynchronous (Default - Recommended)**
```php
$execution = BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->execute(); // Returns immediately, runs in background

// Check status later
$execution->refresh();
```

**Synchronous (For small datasets)**
```php
$execution = BulkAction::on(User::class)
    ->action('update')
    ->ids([1, 2, 3])
    ->sync() // Process immediately, blocks until complete
    ->execute();
```

### Undo Mechanism

The package stores snapshots of records before modification:

```php
// Enable undo for 30 days
BulkAction::on(User::class)
    ->action('update')
    ->where('active', false)
    ->with(['data' => ['status' => 'archived']])
    ->withUndo(days: 30)
    ->execute();
```

**What gets stored:**
- Original field values (for updates)
- Complete record data (for deletes)
- Timestamps and metadata

**Undo limitations:**
- Time-limited (expires after configured days)
- Not available for all actions (e.g., sending emails)
- Requires additional storage space

### Progress Tracking

Progress is calculated based on processed vs total records:

```php
$execution->progress_percentage; // 0-100
$execution->processed_records;    // Records completed
$execution->failed_records;       // Records that failed
$execution->total_records;        // Total to process
```

**Update frequency** is configurable to balance accuracy vs overhead.

### Action Registry

All actions must be registered before use:

```php
// Built-in actions are auto-registered
ActionRegistry::register('delete', DeleteAction::class);
ActionRegistry::register('update', UpdateAction::class);

// Custom actions
ActionRegistry::register('custom_action', CustomAction::class);
```

### Events

The package emits events at key points:

- `BulkActionStarted` - When execution begins
- `BulkActionProgress` - During processing
- `BulkActionCompleted` - On success
- `BulkActionFailed` - On failure
- `BulkActionCancelled` - When cancelled
- `BulkActionUndone` - After undo

Listen to events for monitoring and notifications:

```php
Event::listen(BulkActionCompleted::class, function ($event) {
    $execution = $event->execution;
    // Send notification, log analytics, etc.
});
```

---

## Built-in Actions

The package includes 5 production-ready actions:

### 1. Delete Action

Soft delete or force delete records.

**Basic usage:**
```php
BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->withUndo(days: 30)
    ->execute();
```

**Force delete (permanent):**
```php
BulkAction::on(User::class)
    ->action('delete')
    ->where('deleted_at', '<', now()->subMonths(3))
    ->with(['force' => true])
    ->execute();
```

**Features:**
- ✅ Supports soft delete
- ✅ Force delete option
- ✅ Undo support (for soft deletes)
- ✅ Respects model deletion events

### 2. Update Action

Bulk update fields across records.

```php
BulkAction::on(User::class)
    ->action('update')
    ->where('plan', 'trial')
    ->with(['data' => [
        'plan' => 'free',
        'trial_ended_at' => now(),
        'status' => 'downgraded'
    ]])
    ->withUndo(days: 7)
    ->execute();
```

**Features:**
- ✅ Update multiple fields
- ✅ Undo support with snapshots
- ✅ Validation support
- ✅ Respects model events

**Protected fields:**
- `id`, `created_at` cannot be updated (automatically filtered)

### 3. Restore Action

Restore soft-deleted records.

```php
BulkAction::on(User::class)
    ->action('restore')
    ->ids([1, 2, 3]) // Soft-deleted IDs
    ->execute();
```

**Features:**
- ✅ Restores soft-deleted records
- ✅ Respects model events
- ✅ Works with `SoftDeletes` trait

### 4. Archive Action

Custom archiving with metadata.

```php
BulkAction::on(User::class)
    ->action('archive')
    ->where('last_login_at', '<', now()->subYears(2))
    ->with(['reason' => 'Inactive for 2 years'])
    ->withUndo(days: 90)
    ->execute();
```

**What it does:**
- Sets `status` field to 'archived'
- Stores `archived_at` timestamp
- Saves optional `archive_reason`

**Requirements:**
- Model should have `status`, `archived_at`, and optionally `archive_reason` fields

### 5. Export Action

Export records to CSV, Excel, or PDF.

```php
BulkAction::on(User::class)
    ->action('export')
    ->where('created_at', '>', now()->subMonth())
    ->with([
        'format' => 'csv',
        'columns' => ['id', 'name', 'email', 'created_at'],
        'filename' => 'new-users-' . now()->format('Y-m-d')
    ])
    ->execute();
```

**Supported formats:**
- **CSV** - Built-in, always available
- **Excel** - Requires `maatwebsite/excel` package
- **PDF** - Requires `barryvdh/laravel-dompdf` package

**Features:**
- ✅ Streaming for large datasets
- ✅ Custom column selection
- ✅ Configurable chunk size
- ✅ Automatic cleanup after configured days

**Install optional dependencies:**
```bash
# For Excel export
composer require maatwebsite/excel

# For PDF export
composer require barryvdh/laravel-dompdf
```

---

## Frontend Integration

The package provides ready-to-use components and utilities for all major frontend frameworks.

### Livewire Integration

#### Installation

```bash
php artisan action-engine:install
# Select "Livewire" when prompted
```

Or publish manually:

```bash
php artisan vendor:publish --tag=action-engine-livewire
```

#### Using the Livewire Component

```blade
<livewire:bulk-action-manager 
    :model-class="App\Models\User::class"
    :available-actions="[
        'delete' => [
            'label' => 'Delete Users',
            'color' => 'danger',
            'icon' => 'trash',
            'confirmation' => 'Are you sure you want to delete selected users?'
        ],
        'archive' => [
            'label' => 'Archive Users',
            'color' => 'warning',
            'icon' => 'archive'
        ],
        'update' => [
            'label' => 'Update Status',
            'color' => 'primary',
            'icon' => 'pencil'
        ]
    ]"
    :selected-ids="$selectedUsers"
/>
```

#### Component Features

- ✅ Action selection dropdown
- ✅ Confirmation modals
- ✅ Progress tracking with auto-refresh
- ✅ Success/error notifications
- ✅ Undo button (when applicable)
- ✅ Parameter input forms

#### Custom Livewire Implementation

```php
namespace App\Http\Livewire;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Livewire\Component;

class UserBulkActions extends Component
{
    public $selectedIds = [];
    public $executionUuid;
    public $progress = 0;

    public function executeDelete()
    {
        $execution = BulkAction::on(User::class)
            ->action('delete')
            ->ids($this->selectedIds)
            ->withUndo(days: 30)
            ->execute();

        $this->executionUuid = $execution->uuid;
        
        session()->flash('message', 'Bulk delete started!');
    }

    public function checkProgress()
    {
        if (!$this->executionUuid) return;

        $execution = BulkActionExecution::where('uuid', $this->executionUuid)->first();
        $this->progress = $execution->progress_percentage;

        if ($execution->isCompleted()) {
            session()->flash('message', 'Bulk action completed!');
            $this->executionUuid = null;
        }
    }

    public function render()
    {
        return view('livewire.user-bulk-actions');
    }
}
```

---

### Vue 3 Integration

#### Installation

```bash
php artisan action-engine:install
# Select "Vue" when prompted
```

This publishes the composable to `resources/js/composables/useBulkAction.js`.

#### Using the Composable

```vue
<template>
  <div>
    <button 
      @click="handleDelete" 
      :disabled="isLoading"
      class="btn btn-danger"
    >
      Delete Selected ({{ selectedIds.length }})
    </button>

    <!-- Progress Bar -->
    <div v-if="isInProgress" class="progress">
      <div 
        class="progress-bar" 
        :style="{ width: progress.percentage + '%' }"
      >
        {{ progress.percentage }}%
      </div>
    </div>

    <!-- Status Messages -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <div v-if="isCompleted" class="alert alert-success">
      Action completed! Processed {{ progress.processed }} records.
      <button v-if="canUndo" @click="undoAction">Undo</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useBulkAction } from '@/composables/useBulkAction'

const selectedIds = ref([1, 2, 3, 4, 5])

const { 
  execute, 
  undo, 
  progress, 
  isLoading, 
  isInProgress,
  isCompleted,
  canUndo,
  error 
} = useBulkAction()

const handleDelete = async () => {
  await execute({
    action: 'delete',
    model: 'App\\Models\\User',
    filters: { ids: selectedIds.value },
    options: { 
      with_undo: true,
      undo_days: 30
    }
  })
}

const undoAction = async () => {
  await undo()
}
</script>
```

#### Composable API

```javascript
const {
  // Methods
  execute,        // Execute bulk action
  undo,           // Undo action
  cancel,         // Cancel running action
  checkProgress,  // Manually check progress
  
  // State
  execution,      // Current execution object
  progress,       // Progress data { percentage, processed, total, failed }
  isLoading,      // Boolean: action is executing
  isInProgress,   // Boolean: action is processing
  isCompleted,    // Boolean: action completed
  isFailed,       // Boolean: action failed
  canUndo,        // Boolean: action can be undone
  error           // Error message if failed
} = useBulkAction()
```

---

### React Integration

#### Installation

```bash
php artisan action-engine:install
# Select "React" when prompted
```

This publishes the hook to `resources/js/hooks/useBulkAction.js`.

#### Using the Hook

```jsx
import React, { useState } from 'react'
import { useBulkAction } from '@/hooks/useBulkAction'

function BulkDeleteButton({ selectedIds }) {
  const { 
    execute, 
    undo,
    progress, 
    isLoading, 
    isInProgress,
    isCompleted,
    canUndo,
    error 
  } = useBulkAction()

  const handleDelete = async () => {
    await execute({
      action: 'delete',
      model: 'App\\Models\\User',
      filters: { ids: selectedIds },
      options: { with_undo: true }
    })
  }

  return (
    <div>
      <button 
        onClick={handleDelete} 
        disabled={isLoading}
        className="btn btn-danger"
      >
        Delete Selected ({selectedIds.length})
      </button>

      {isInProgress && (
        <div className="progress">
          <div 
            className="progress-bar" 
            style={{ width: `${progress.percentage}%` }}
          >
            {progress.percentage}%
          </div>
        </div>
      )}

      {error && (
        <div className="alert alert-danger">{error}</div>
      )}

      {isCompleted && (
        <div className="alert alert-success">
          Action completed! Processed {progress.processed} records.
          {canUndo && (
            <button onClick={undo} className="btn btn-sm btn-link">
              Undo
            </button>
          )}
        </div>
      )}
    </div>
  )
}

export default BulkDeleteButton
```

#### TypeScript Support

```typescript
interface BulkActionOptions {
  action: string
  model: string
  filters: {
    ids?: number[]
    where?: Record<string, any>
  }
  options?: {
    with_undo?: boolean
    undo_days?: number
    batch_size?: number
    sync?: boolean
  }
}

interface Progress {
  percentage: number
  processed: number
  total: number
  failed: number
  status: string
}
```

---

### Alpine.js Integration

#### Installation

```bash
php artisan action-engine:install
# Select "Alpine.js" when prompted
```

#### Using Alpine Components

```html
<div x-data="bulkAction()">
  <!-- Action Button -->
  <button 
    @click="execute({ 
      action: 'delete', 
      model: 'App\\Models\\User', 
      filters: { ids: selectedIds } 
    })"
    :disabled="isLoading"
    class="btn btn-danger"
  >
    <span x-show="!isLoading">Delete Selected</span>
    <span x-show="isLoading">Processing...</span>
  </button>

  <!-- Progress Bar -->
  <template x-if="isInProgress">
    <div class="progress mt-3">
      <div 
        class="progress-bar" 
        :style="`width: ${progress.percentage}%`"
        x-text="`${progress.percentage}%`"
      ></div>
    </div>
  </template>

  <!-- Status Messages -->
  <template x-if="isCompleted">
    <div class="alert alert-success mt-3">
      <p>Action completed! Processed <span x-text="progress.processed"></span> records.</p>
      <button x-show="canUndo" @click="undo()" class="btn btn-sm btn-warning">
        Undo
      </button>
    </div>
  </template>

  <template x-if="error">
    <div class="alert alert-danger mt-3" x-text="error"></div>
  </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('bulkAction', () => ({
    executionUuid: null,
    isLoading: false,
    isInProgress: false,
    isCompleted: false,
    canUndo: false,
    progress: { percentage: 0, processed: 0, total: 0, failed: 0 },
    error: null,
    pollInterval: null,

    async execute(params) {
      this.isLoading = true
      this.error = null

      try {
        const response = await fetch('/api/bulk-actions', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify(params)
        })

        const data = await response.json()
        this.executionUuid = data.data.uuid
        this.isInProgress = true
        this.startPolling()
      } catch (err) {
        this.error = err.message
      } finally {
        this.isLoading = false
      }
    },

    startPolling() {
      this.pollInterval = setInterval(() => this.checkProgress(), 2000)
    },

    async checkProgress() {
      const response = await fetch(`/api/bulk-actions/${this.executionUuid}/progress`)
      const data = await response.json()
      
      this.progress = data.data
      
      if (data.data.status === 'completed') {
        this.isCompleted = true
        this.isInProgress = false
        this.canUndo = data.data.can_undo
        clearInterval(this.pollInterval)
      }
    },

    async undo() {
      await fetch(`/api/bulk-actions/${this.executionUuid}/undo`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      })
      this.canUndo = false
    }
  }))
})
</script>
```

---

### Filament Integration

The package includes 5 ready-to-use Filament bulk actions.

#### Installation

```bash
php artisan action-engine:install
# Select "Filament" when prompted
```

Or publish manually:

```bash
php artisan vendor:publish --tag=action-engine-filament
```

#### Basic Usage in Resources

```php
use DhruvilNagar\ActionEngine\Filament\Actions\BulkDeleteAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkArchiveAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkRestoreAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkUpdateAction;
use DhruvilNagar\ActionEngine\Filament\Actions\BulkExportAction;
use Filament\Tables\Table;

class UserResource extends Resource
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Your columns...
            ])
            ->bulkActions([
                // Delete with undo
                BulkDeleteAction::make()
                    ->withUndo(30),

                // Archive users
                BulkArchiveAction::make(),

                // Restore soft-deleted
                BulkRestoreAction::make()
                    ->visible(fn () => request()->routeIs('*.trashed')),

                // Bulk update fields
                BulkUpdateAction::make()
                    ->updateFields([
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->required(),
                    ]),

                // Export to CSV/Excel
                BulkExportAction::make()
                    ->withColumns(['id', 'name', 'email', 'created_at']),
            ]);
    }
}
```

#### Available Filament Actions

**1. BulkDeleteAction**
```php
BulkDeleteAction::make()
    ->withUndo(60)           // Undo window in days
    ->forceDelete()          // Permanent delete
    ->requiresConfirmation() // Show confirmation modal
```

**2. BulkArchiveAction**
```php
BulkArchiveAction::make()
    ->withUndo(90)
```

**3. BulkRestoreAction**
```php
BulkRestoreAction::make()
    ->successNotificationTitle('Users restored!')
```

**4. BulkUpdateAction**
```php
BulkUpdateAction::make()
    ->updateFields([
        TextInput::make('notes')
            ->maxLength(500),
        Toggle::make('verified'),
        DatePicker::make('expires_at'),
    ])
```

**5. BulkExportAction**
```php
BulkExportAction::make()
    ->withColumns(['id', 'name', 'email'])
    ->formats(['csv', 'xlsx']) // Available formats
```

#### Custom Filament Actions

```php
namespace App\Filament\Actions;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction as FilamentBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class BulkSuspendAction extends FilamentBulkAction
{
    public static function getDefaultName(): ?string
    {
        return 'bulk_suspend';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Suspend Selected')
            ->color('warning')
            ->icon('heroicon-o-no-symbol')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reason')
                    ->label('Suspension Reason')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                $execution = BulkAction::on($records->first()::class)
                    ->ids($records->pluck('id')->toArray())
                    ->action('suspend')
                    ->with(['reason' => $data['reason']])
                    ->withUndo(days: 14)
                    ->execute();

                Notification::make()
                    ->title('Users suspended')
                    ->body("Processing {$execution->total_records} users.")
                    ->success()
                    ->send();
            });
    }
}
```

---

### Blade Templates (Traditional)

For traditional Blade views without Livewire.

#### Basic Form

```blade
<form action="{{ route('action-engine.execute') }}" method="POST">
    @csrf
    
    <input type="hidden" name="model" value="App\Models\User">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="filters[ids]" value="{{ implode(',', $selectedIds) }}">
    <input type="hidden" name="options[with_undo]" value="1">
    
    <button type="submit" class="btn btn-danger">
        Delete Selected Users
    </button>
</form>
```

#### With Progress Tracking

```blade
<!-- Include the progress bar component -->
@include('action-engine::blade.progress-bar', [
    'executionUuid' => $execution->uuid,
    'autoRefresh' => true,
    'refreshInterval' => 2000
])
```

#### Controller Example

```php
namespace App\Http\Controllers;

use DhruvilNagar\ActionEngine\Facades\BulkAction;
use Illuminate\Http\Request;

class BulkActionsController extends Controller
{
    public function execute(Request $request)
    {
        $execution = BulkAction::on($request->model)
            ->action($request->action)
            ->ids($request->input('filters.ids'))
            ->withUndo(days: 30)
            ->execute();

        return redirect()->route('bulk-actions.progress', $execution->uuid)
            ->with('success', 'Bulk action started!');
    }

    public function progress($uuid)
    {
        $execution = BulkActionExecution::where('uuid', $uuid)->firstOrFail();
        
        return view('bulk-actions.progress', compact('execution'));
    }
}
```

---

## Custom Actions

Create custom actions tailored to your business logic.

### Method 1: Closure-Based Actions

Quick and simple for straightforward operations.

```php
use DhruvilNagar\ActionEngine\Facades\ActionRegistry;

// In AppServiceProvider::boot()
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
```

**Usage:**
```php
BulkAction::on(User::class)
    ->action('send_notification')
    ->ids([1, 2, 3])
    ->with(['message' => 'Your account has been updated'])
    ->execute();
```

### Method 2: Class-Based Actions

Recommended for complex operations with validation and undo support.

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
        $record->notify(new AccountSuspendedNotification($parameters['reason']));

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

**Register in AppServiceProvider:**
```php
use App\Actions\SuspendUserAction;
use DhruvilNagar\ActionEngine\Facades\ActionRegistry;

public function boot()
{
    ActionRegistry::register('suspend_user', SuspendUserAction::class, [
        'label' => 'Suspend User',
        'supports_undo' => true,
        'confirmation_required' => true,
    ]);
}
```

### Method 3: Advanced Action with Custom Logic

```php
namespace App\Actions;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferOwnershipAction implements ActionInterface
{
    public function execute(Model $record, array $parameters = []): bool
    {
        DB::beginTransaction();

        try {
            $newOwner = User::findOrFail($parameters['new_owner_id']);
            
            // Transfer ownership
            $record->update([
                'owner_id' => $newOwner->id,
                'transferred_at' => now(),
                'transferred_by' => auth()->id(),
            ]);

            // Update related records
            $record->projects()->update(['owner_id' => $newOwner->id]);
            
            // Log the transfer
            Log::info("Ownership transferred", [
                'record_id' => $record->id,
                'from' => $record->getOriginal('owner_id'),
                'to' => $newOwner->id,
            ]);

            // Notify both parties
            $record->owner->notify(new OwnershipTransferredNotification($record, $newOwner));
            $newOwner->notify(new OwnershipReceivedNotification($record));

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Ownership transfer failed", [
                'record_id' => $record->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getName(): string
    {
        return 'transfer_ownership';
    }

    public function getLabel(): string
    {
        return 'Transfer Ownership';
    }

    public function supportsUndo(): bool
    {
        return true;
    }

    public function getUndoType(): ?string
    {
        return 'custom';
    }

    public function undo(Model $record, array $originalData): bool
    {
        // Custom undo logic
        $record->update([
            'owner_id' => $originalData['owner_id'],
            'transferred_at' => null,
            'transferred_by' => null,
        ]);

        $record->projects()->update(['owner_id' => $originalData['owner_id']]);

        return true;
    }

    public function validateParameters(array $parameters): array
    {
        return validator($parameters, [
            'new_owner_id' => 'required|exists:users,id',
        ])->validate();
    }

    public function getUndoFields(): array
    {
        return ['owner_id', 'transferred_at', 'transferred_by'];
    }
}
```

### Action Interface Methods

```php
interface ActionInterface
{
    // Required methods
    public function execute(Model $record, array $parameters = []): bool;
    public function getName(): string;
    public function getLabel(): string;
    public function supportsUndo(): bool;
    
    // Optional methods
    public function getUndoType(): ?string; // 'update', 'delete', or 'custom'
    public function validateParameters(array $parameters): array;
    public function getUndoFields(): array;
    public function undo(Model $record, array $originalData): bool;
}
```

### Registering Multiple Actions

```php
public function boot()
{
    $actions = [
        'approve' => ApproveAction::class,
        'reject' => RejectAction::class,
        'publish' => PublishAction::class,
        'unpublish' => UnpublishAction::class,
    ];

    foreach ($actions as $name => $class) {
        ActionRegistry::register($name, $class);
    }
}
```

---

## API Reference

Complete REST API for executing and managing bulk actions.

### Base URL

```
/api/bulk-actions
```

All endpoints require authentication (default: `auth:sanctum`).

### Endpoints

#### 1. List User's Bulk Actions

```http
GET /api/bulk-actions
```

**Query Parameters:**
- `status` - Filter by status (pending, processing, completed, failed, cancelled)
- `action` - Filter by action name
- `per_page` - Results per page (default: 15)
- `page` - Page number

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "uuid": "9a7f8c3d-2e4b-4f5a-9c8d-1e2f3a4b5c6d",
      "model_class": "App\\Models\\User",
      "action": "delete",
      "status": "completed",
      "total_records": 1000,
      "processed_records": 1000,
      "failed_records": 0,
      "progress_percentage": 100,
      "can_undo": true,
      "undo_expires_at": "2026-02-18T10:30:00.000000Z",
      "created_at": "2026-01-19T10:30:00.000000Z",
      "completed_at": "2026-01-19T10:35:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 25
  }
}
```

#### 2. Get Available Actions

```http
GET /api/bulk-actions/actions
```

**Response:**
```json
{
  "success": true,
  "data": {
    "delete": {
      "name": "delete",
      "label": "Delete",
      "supports_undo": true,
      "confirmation_required": true
    },
    "update": {
      "name": "update",
      "label": "Update",
      "supports_undo": true
    }
  }
}
```

#### 3. Execute Bulk Action

```http
POST /api/bulk-actions
```

**Request Body:**
```json
{
  "model": "App\\Models\\User",
  "action": "delete",
  "filters": {
    "ids": [1, 2, 3, 4, 5],
    "where": {
      "status": "inactive"
    }
  },
  "options": {
    "with_undo": true,
    "undo_days": 30,
    "batch_size": 500,
    "sync": false
  },
  "parameters": {
    "reason": "Cleanup inactive accounts"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "uuid": "9a7f8c3d-2e4b-4f5a-9c8d-1e2f3a4b5c6d",
    "status": "pending",
    "total_records": 5,
    "message": "Bulk action queued successfully"
  }
}
```

#### 4. Preview Action (Dry Run)

```http
POST /api/bulk-actions/preview
```

**Request Body:** Same as execute endpoint

**Response:**
```json
{
  "success": true,
  "data": {
    "count": 150,
    "sample": [
      {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
      }
    ],
    "affected_records": 150
  }
}
```

#### 5. Get Execution Details

```http
GET /api/bulk-actions/{uuid}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "uuid": "9a7f8c3d-2e4b-4f5a-9c8d-1e2f3a4b5c6d",
    "model_class": "App\\Models\\User",
    "action": "delete",
    "status": "processing",
    "total_records": 1000,
    "processed_records": 450,
    "failed_records": 2,
    "progress_percentage": 45.0,
    "batch_size": 100,
    "is_sync": false,
    "can_undo": true,
    "undo_expires_at": "2026-02-18T10:30:00.000000Z",
    "created_at": "2026-01-19T10:30:00.000000Z",
    "started_at": "2026-01-19T10:30:05.000000Z"
  }
}
```

#### 6. Get Progress

```http
GET /api/bulk-actions/{uuid}/progress
```

**Response:**
```json
{
  "success": true,
  "data": {
    "progress_percentage": 45.5,
    "processed_records": 455,
    "total_records": 1000,
    "failed_records": 2,
    "status": "processing",
    "estimated_completion": "2026-01-19T10:40:00.000000Z"
  }
}
```

#### 7. Cancel Action

```http
POST /api/bulk-actions/{uuid}/cancel
```

**Response:**
```json
{
  "success": true,
  "message": "Bulk action cancelled successfully"
}
```

#### 8. Undo Action

```http
POST /api/bulk-actions/{uuid}/undo
```

**Response:**
```json
{
  "success": true,
  "message": "Bulk action undone successfully",
  "data": {
    "undone_records": 1000
  }
}
```

#### 9. Check Undo Availability

```http
GET /api/bulk-actions/{uuid}/undo
```

**Response:**
```json
{
  "success": true,
  "data": {
    "can_undo": true,
    "expires_at": "2026-02-18T10:30:00.000000Z",
    "time_remaining": "29 days"
  }
}
```

### Error Responses

**400 Bad Request:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "action": ["The action field is required."]
  }
}
```

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

**403 Forbidden:**
```json
{
  "success": false,
  "message": "You are not authorized to perform this action"
}
```

**404 Not Found:**
```json
{
  "success": false,
  "message": "Bulk action execution not found"
}
```

**429 Too Many Requests:**
```json
{
  "success": false,
  "message": "Rate limit exceeded. You have too many concurrent actions."
}
```

### Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **Execute endpoint**: 60 requests per minute per user
- **Progress endpoint**: Unlimited (for polling)
- **Other endpoints**: 60 requests per minute per user

Configure in `config/action-engine.php`:

```php
'routes' => [
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
],
```

### Authentication

Default authentication uses Laravel Sanctum. Customize in config:

```php
'routes' => [
    'middleware' => [
        'api' => ['api', 'auth:sanctum'],
        'web' => ['web', 'auth'],
    ],
],
```

---

## Advanced Usage

### Action Chaining

Execute multiple actions sequentially with conditional logic.

```php
// Chain actions with callbacks
$execution1 = BulkAction::on(User::class)
    ->action('update')
    ->where('subscription_expires_at', '<', now())
    ->with(['data' => ['plan' => 'free']])
    ->onComplete(function ($execution) {
        // Chain second action after first completes
        BulkAction::on(User::class)
            ->ids($execution->getAffectedIds())
            ->action('send_notification')
            ->with(['message' => 'Your subscription has expired'])
            ->execute();
    })
    ->execute();
```

### Progress Callbacks

Monitor progress with custom callbacks:

```php
$execution = BulkAction::on(User::class)
    ->action('update')
    ->where('active', true)
    ->with(['data' => ['last_checked' => now()]])
    ->onProgress(function ($progress) {
        Log::info("Progress update", [
            'percentage' => $progress['percentage'],
            'processed' => $progress['processed'],
            'total' => $progress['total'],
        ]);
        
        // Update external dashboard
        Redis::set("bulk_action_progress", $progress['percentage']);
    })
    ->onComplete(function ($execution) {
        // Notify admin
        User::role('admin')->each(function ($admin) use ($execution) {
            $admin->notify(new BulkActionCompletedNotification($execution));
        });
    })
    ->onFailure(function ($execution, $error) {
        Log::error("Bulk action failed", [
            'uuid' => $execution->uuid,
            'error' => $error->getMessage(),
        ]);
        
        // Alert via Slack
        Slack::error("Bulk action failed: " . $error->getMessage());
    })
    ->execute();
```

### Scheduled Actions with Cron

Set up recurring bulk actions:

```php
// In routes/console.php or App\Console\Kernel

use Illuminate\Support\Facades\Schedule;
use DhruvilNagar\ActionEngine\Facades\BulkAction;

// Daily cleanup at 2 AM
Schedule::call(function () {
    BulkAction::on(User::class)
        ->action('delete')
        ->where('status', 'pending_deletion')
        ->where('marked_for_deletion_at', '<', now()->subDays(30))
        ->execute();
})->daily()->at('02:00')->timezone('UTC');

// Weekly archive on Sundays
Schedule::call(function () {
    BulkAction::on(Log::class)
        ->action('archive')
        ->where('created_at', '<', now()->subMonths(6))
        ->with(['reason' => 'Automatic 6-month archive'])
        ->withUndo(days: 30)
        ->execute();
})->weekly()->sundays()->at('03:00');

// Monthly reports
Schedule::call(function () {
    BulkAction::on(Order::class)
        ->action('export')
        ->whereBetween('created_at', [
            now()->subMonth()->startOfMonth(),
            now()->subMonth()->endOfMonth()
        ])
        ->with([
            'format' => 'xlsx',
            'columns' => ['id', 'customer_name', 'total', 'status', 'created_at'],
            'filename' => 'monthly-orders-' . now()->subMonth()->format('Y-m')
        ])
        ->execute();
})->monthlyOn(1, '09:00');
```

### Custom Authorization Logic

Implement fine-grained authorization:

```php
// Policy-based
BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->authorize(function ($user, $modelClass, $action) {
        return $user->can('bulkDelete', $modelClass);
    })
    ->execute();

// Role-based
BulkAction::on(User::class)
    ->action('update')
    ->ids([1, 2, 3])
    ->authorize(function ($user) {
        return $user->hasRole(['admin', 'manager']);
    })
    ->execute();

// Custom per-record authorization
ActionRegistry::register('sensitive_update', function ($record, $params) use ($user) {
    if (!$user->can('update', $record)) {
        throw new UnauthorizedException("Not authorized for record {$record->id}");
    }
    
    $record->update($params['data']);
    return true;
});
```

### Real-time Broadcasting

Enable WebSocket broadcasting for real-time updates:

**1. Configure broadcasting:**

```env
BROADCAST_DRIVER=pusher
ACTION_ENGINE_BROADCASTING_ENABLED=true

PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
```

**2. Listen to events in JavaScript:**

```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true
})

// Listen to bulk action progress
Echo.private(`bulk-action.${executionUuid}`)
    .listen('.BulkActionProgress', (data) => {
        console.log(`Progress: ${data.progress_percentage}%`)
        updateProgressBar(data.progress_percentage)
    })
    .listen('.BulkActionCompleted', (data) => {
        console.log('Action completed!')
        showSuccessMessage()
    })
    .listen('.BulkActionFailed', (data) => {
        console.log('Action failed:', data.error)
        showErrorMessage(data.error)
    })
```

### Export Large Datasets

Handle exports of millions of records efficiently:

```php
// Streaming export (memory efficient)
$execution = BulkAction::on(User::class)
    ->action('export')
    ->where('created_at', '>', now()->subYear())
    ->with([
        'format' => 'csv',
        'columns' => ['id', 'name', 'email', 'created_at'],
        'filename' => 'users-export-' . now()->format('Y-m-d'),
        'chunk_size' => 1000, // Process 1000 at a time
    ])
    ->batchSize(5000) // 5000 records per queue job
    ->execute();

// Download the export after completion
$execution->refresh();
if ($execution->isCompleted()) {
    $exportPath = $execution->getMetadata('export_path');
    return Storage::download($exportPath);
}
```

### Using the HasBulkActions Trait

Add convenient methods to your models:

```php
use DhruvilNagar\ActionEngine\Traits\HasBulkActions;

class User extends Model
{
    use HasBulkActions;
}

// Now you can use:
User::bulkDelete([1, 2, 3]);
User::bulkUpdate([1, 2, 3], ['status' => 'active']);
User::bulkArchive([1, 2, 3], 'Cleanup');

// Get action history
$history = User::getBulkActionHistory();

// Get undoable actions
$undoable = User::getUndoableBulkActions();
```

### Transaction Safety

Wrap actions in database transactions:

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $execution = BulkAction::on(User::class)
        ->action('update')
        ->ids([1, 2, 3])
        ->with(['data' => ['status' => 'active']])
        ->sync() // Must be synchronous for transactions
        ->execute();
        
    if ($execution->isFailed()) {
        throw new \Exception('Bulk action failed');
    }
    
    // Additional database operations
    Log::create([
        'action' => 'bulk_update',
        'execution_uuid' => $execution->uuid,
    ]);
});
```

### Monitoring and Alerting

Integrate with monitoring tools:

```php
// In EventServiceProvider
protected $listen = [
    BulkActionFailed::class => [
        SendSlackAlert::class,
        LogToSentry::class,
    ],
    BulkActionCompleted::class => [
        UpdateMetrics::class,
    ],
];

// SendSlackAlert listener
class SendSlackAlert
{
    public function handle(BulkActionFailed $event)
    {
        $execution = $event->execution;
        
        Slack::send([
            'channel' => '#alerts',
            'text' => "🚨 Bulk Action Failed",
            'attachments' => [
                [
                    'color' => 'danger',
                    'fields' => [
                        ['title' => 'UUID', 'value' => $execution->uuid, 'short' => true],
                        ['title' => 'Action', 'value' => $execution->action, 'short' => true],
                        ['title' => 'Error', 'value' => $execution->error_message],
                    ]
                ]
            ]
        ]);
    }
}
```

---

## Best Practices

### 1. Batch Size Optimization

Choose appropriate batch sizes based on your data:

```php
// Small, simple operations (e.g., status updates)
->batchSize(1000)

// Complex operations with relationships
->batchSize(500)

// Operations with external API calls
->batchSize(100)

// Very large datasets (millions of records)
->batchSize(5000)
```

**Rule of thumb:** 
- Higher batch size = faster completion, less overhead
- Lower batch size = more frequent progress updates, better error isolation

### 2. Always Use Queues for Large Operations

```php
// ❌ DON'T: Synchronous for large datasets
BulkAction::on(User::class)
    ->action('update')
    ->where('active', true) // Could be millions
    ->sync() // Will timeout!
    ->execute();

// ✅ DO: Asynchronous for large datasets
BulkAction::on(User::class)
    ->action('update')
    ->where('active', true)
    ->execute(); // Queued automatically
```

### 3. Provide Undo When Possible

```php
// ✅ DO: Enable undo for destructive operations
BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive')
    ->withUndo(days: 30) // Safety net
    ->execute();
```

### 4. Use Dry Run Before Execution

```php
// ✅ DO: Preview before executing
$preview = BulkAction::on(User::class)
    ->action('delete')
    ->where('last_login_at', '<', now()->subYears(2))
    ->dryRun()
    ->execute();

if ($preview->dry_run_results['count'] < 1000) {
    // Proceed with actual execution
    BulkAction::on(User::class)
        ->action('delete')
        ->where('last_login_at', '<', now()->subYears(2))
        ->withUndo()
        ->execute();
}
```

### 5. Implement Proper Error Handling

```php
try {
    $execution = BulkAction::on(User::class)
        ->action('update')
        ->ids($selectedIds)
        ->with(['data' => $updateData])
        ->onFailure(function ($execution, $error) {
            Log::error('Bulk action failed', [
                'uuid' => $execution->uuid,
                'error' => $error->getMessage(),
                'trace' => $error->getTraceAsString(),
            ]);
            
            // Notify relevant parties
            Mail::to(config('app.admin_email'))
                ->send(new BulkActionFailedMail($execution, $error));
        })
        ->execute();
        
} catch (UnauthorizedBulkActionException $e) {
    return response()->json(['error' => 'Unauthorized'], 403);
} catch (RateLimitExceededException $e) {
    return response()->json(['error' => 'Too many requests'], 429);
} catch (\Exception $e) {
    Log::error('Unexpected error', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Internal error'], 500);
}
```

### 6. Validate Parameters

```php
// ✅ DO: Validate in custom actions
class UpdateUserAction implements ActionInterface
{
    public function validateParameters(array $parameters): array
    {
        return validator($parameters, [
            'status' => 'required|in:active,inactive,suspended',
            'reason' => 'required_if:status,suspended|string|max:500',
            'expires_at' => 'nullable|date|after:today',
        ])->validate();
    }
    
    public function execute(Model $record, array $parameters = []): bool
    {
        $validated = $this->validateParameters($parameters);
        $record->update($validated);
        return true;
    }
}
```

### 7. Use Specific IDs When Possible

```php
// ✅ BETTER: Use specific IDs (more predictable)
BulkAction::on(User::class)
    ->action('delete')
    ->ids([1, 2, 3, 4, 5])
    ->execute();

// ⚠️ CAUTION: Where clauses can affect more records than expected
BulkAction::on(User::class)
    ->action('delete')
    ->where('status', 'inactive') // Could match thousands
    ->execute();
```

### 8. Schedule Resource-Intensive Operations

```php
// ✅ DO: Schedule for off-peak hours
BulkAction::on(User::class)
    ->action('export')
    ->where('created_at', '>', now()->subMonth())
    ->scheduleFor(Carbon::tomorrow()->hour(2)) // 2 AM
    ->execute();
```

### 9. Clean Up Old Data Regularly

```bash
# Add to scheduler
php artisan action-engine:cleanup

# Or manually with custom retention
php artisan action-engine:cleanup --days=30
```

### 10. Monitor Performance

```php
// Track execution time
$startTime = microtime(true);

$execution = BulkAction::on(User::class)
    ->action('update')
    ->where('active', true)
    ->execute();

// Wait for completion (for testing/monitoring)
while ($execution->isInProgress()) {
    sleep(2);
    $execution->refresh();
}

$duration = microtime(true) - $startTime;

Log::info('Bulk action performance', [
    'uuid' => $execution->uuid,
    'total_records' => $execution->total_records,
    'duration_seconds' => $duration,
    'records_per_second' => $execution->total_records / $duration,
]);
```

---

## Troubleshooting

### Issue: Actions Not Processing

**Symptoms:** Execution stays in "pending" status

**Solutions:**

1. **Check if queue worker is running:**
```bash
php artisan queue:work --queue=bulk-actions
```

2. **Verify queue configuration:**
```env
QUEUE_CONNECTION=redis
ACTION_ENGINE_QUEUE_NAME=bulk-actions
```

3. **Check for failed jobs:**
```bash
php artisan queue:failed
php artisan queue:retry all
```

### Issue: Out of Memory Errors

**Symptoms:** Process killed, memory exhausted errors

**Solutions:**

1. **Reduce batch size:**
```php
->batchSize(100) // Instead of 1000
```

2. **Increase PHP memory limit:**
```ini
memory_limit = 512M
```

3. **Use chunking for relationships:**
```php
ActionRegistry::register('notify_with_posts', function ($user) {
    $user->posts()->chunk(100, function ($posts) {
        // Process posts in chunks
    });
});
```

### Issue: Progress Not Updating

**Symptoms:** Progress stuck at 0%

**Solutions:**

1. **Check cache configuration:**
```env
CACHE_DRIVER=redis # Not 'array' in production
```

2. **Verify progress tracking is enabled:**
```php
'progress' => [
    'update_frequency' => 10,
],
```

3. **Clear cache:**
```bash
php artisan cache:clear
```

### Issue: Undo Not Working

**Symptoms:** Cannot undo actions

**Solutions:**

1. **Check if undo is enabled globally:**
```php
'undo' => [
    'enabled' => true,
],
```

2. **Verify undo was enabled for the action:**
```php
->withUndo(days: 30)
```

3. **Check if undo has expired:**
```php
if ($execution->canUndo()) {
    $execution->undo();
} else {
    echo $execution->getUndoStatus(); // "Expired" or "Already undone"
}
```

### Issue: High Database Load

**Symptoms:** Database slow during bulk operations

**Solutions:**

1. **Add database indexes:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->index(['status', 'last_login_at']);
});
```

2. **Reduce concurrent batch processing:**
```php
'rate_limiting' => [
    'max_concurrent_actions' => 3, // Reduce from 5
],
```

3. **Use read replicas:**
```php
// In your model
protected $connection = 'mysql_read';
```

### Issue: WebSocket Broadcasting Not Working

**Solutions:**

1. **Verify broadcasting is configured:**
```env
BROADCAST_DRIVER=pusher
ACTION_ENGINE_BROADCASTING_ENABLED=true
```

2. **Check queue for broadcasts:**
```bash
php artisan queue:work --queue=broadcasts
```

3. **Test broadcasting:**
```bash
php artisan tinker
>>> event(new \DhruvilNagar\ActionEngine\Events\BulkActionProgress($execution))
```

### Common Errors and Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| `Action not registered` | Custom action not registered | Register in AppServiceProvider |
| `Unauthorized` | Missing permissions | Check policies and authorization |
| `Rate limit exceeded` | Too many concurrent actions | Wait or increase limit in config |
| `Undo expired` | Undo period passed | Increase undo_expiry_days |
| `Invalid model class` | Wrong model name | Use full namespace |
| `Batch size too large` | Memory issues | Reduce batch_size |

---

## Contributing

We welcome contributions! Here's how to get started:

### Development Setup

```bash
# Clone the repository
git clone https://github.com/dhruvilnagar/laravel-action-engine.git
cd laravel-action-engine

# Install dependencies
composer install

# Run tests
composer test

# Run code analysis
composer analyse

# Format code
composer format
```

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Feature tests only
composer test:feature

# With coverage
composer test:coverage
```

### Code Standards

- **PSR-12** coding standard
- **PHP 8.1+** type hints and features
- **100% test coverage** for new features
- **PHPDoc blocks** for all public methods
- **Descriptive variable names** and comments

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Commit with descriptive messages
6. Push to your fork
7. Open a Pull Request

### Reporting Issues

When reporting issues, please include:

- Laravel version
- PHP version
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Relevant code snippets
- Error messages and stack traces

---

## Performance Benchmarks

Based on testing with various dataset sizes:

| Records | Batch Size | Duration | Memory | Records/sec |
|---------|-----------|----------|---------|-------------|
| 1,000 | 100 | 5s | 32MB | 200 |
| 10,000 | 500 | 45s | 64MB | 222 |
| 100,000 | 1,000 | 6m | 128MB | 277 |
| 1,000,000 | 5,000 | 58m | 256MB | 287 |

**Test environment:** PHP 8.2, Laravel 11, Redis queue, MySQL 8.0

---

## Changelog

### Version 1.0.0 (January 2026)

**Added:**
- Complete bulk action engine with fluent API
- 5 built-in actions (delete, update, restore, archive, export)
- Full undo functionality with snapshots
- Real-time progress tracking
- Scheduled action support
- Comprehensive audit logging
- Rate limiting and authorization
- 6 frontend integrations (Livewire, Vue, React, Alpine, Filament, Blade)
- REST API with 9 endpoints
- Database factories for testing
- 98% test coverage
- Complete documentation

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

---

## Support

- **Documentation:** [Full Documentation](https://github.com/dhruvilnagar/laravel-action-engine)
- **Issues:** [GitHub Issues](https://github.com/dhruvilnagar/laravel-action-engine/issues)
- **Discussions:** [GitHub Discussions](https://github.com/dhruvilnagar/laravel-action-engine/discussions)
- **Email:** ddhruvill264@gmail.com

---

## Credits

**Author:** Dhruvil Nagar

**Contributors:** See [CONTRIBUTORS.md](CONTRIBUTORS.md)

**Special Thanks:**
- Laravel framework team
- All package contributors
- Community testers and feedback providers

---

## FAQ

### Q: Can I use this with Livewire 2?
**A:** The package is designed for Livewire 3. For Livewire 2, you may need to adapt the components.

### Q: Does it work with MongoDB?
**A:** The package is designed for SQL databases. MongoDB support would require significant modifications.

### Q: Can I use multiple queue connections?
**A:** Yes, configure different connections in the config file for different action types.

### Q: How do I handle very large exports (10M+ records)?
**A:** Use streaming exports with appropriate chunk sizes and consider splitting into multiple files.

### Q: Can I pause and resume bulk actions?
**A:** Not currently supported. You can cancel and restart, but resume functionality is on the roadmap.

### Q: Is it safe to use in production?
**A:** Yes, the package has 98% test coverage and includes comprehensive error handling and safeguards.

### Q: Can I use this for real-time operations?
**A:** While optimized for background processing, small operations can run synchronously with `->sync()`.

### Q: How do I migrate from another bulk action solution?
**A:** Create custom actions that replicate your existing logic, then gradually migrate operations.

---

**Thank you for using Laravel Action Engine!** 🚀

For the latest updates, star us on [GitHub](https://github.com/dhruvilnagar/laravel-action-engine).

