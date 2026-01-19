# Laravel Bulk Actions Core - Development Specification

## Package Overview

**Package Name:** `yourname/laravel-bulk-actions`  
**Description:** A framework-agnostic Laravel package for managing bulk operations with queue support, progress tracking, undo functionality, and scheduled execution.  
**Target Laravel Version:** 10.x and 11.x  
**PHP Version:** 8.1+

---

## Table of Contents

1. [Problem Statement](#problem-statement)
2. [Core Features](#core-features)
3. [Architecture Overview](#architecture-overview)
4. [Directory Structure](#directory-structure)
5. [Database Schema](#database-schema)
6. [Core Components](#core-components)
7. [API Design](#api-design)
8. [Integration Examples](#integration-examples)
9. [Configuration](#configuration)
10. [Testing Requirements](#testing-requirements)
11. [Documentation Requirements](#documentation-requirements)

---

## Problem Statement

Developers frequently need to perform bulk operations (delete, update, export, custom actions) on multiple records. Current solutions either:

- Lock developers into specific UI frameworks (Filament)
- Lack enterprise features (progress tracking, undo, scheduling)
- Don't handle large datasets efficiently
- Require significant boilerplate code for each implementation

**This package solves these problems by providing a standalone, framework-agnostic engine for bulk operations.**

---

## Core Features

### Phase 1 (MVP - Priority 1)

1. **Fluent API for defining bulk actions**
   - Simple, readable syntax
   - Method chaining support
   - Type-safe action definitions

2. **Queue Integration**
   - Automatic batching for large datasets
   - Configurable batch sizes
   - Progress tracking per batch

3. **Progress Tracking**
   - Real-time progress updates
   - Polling endpoint support
   - WebSocket support (optional)
   - Event broadcasting

4. **Basic Undo Functionality**
   - Store action metadata and affected IDs
   - Time-limited undo (configurable expiry)
   - Automatic cleanup of expired undo data

5. **Authorization Support**
   - Policy-based authorization
   - Per-action permission checks
   - Configurable authorization handlers

### Phase 2 (Enhanced Features - Priority 2)

6. **Scheduled Bulk Actions**
   - Defer execution to specific time
   - Recurring bulk actions
   - Cron-based scheduling

7. **Dry Run Mode**
   - Preview what will happen without executing
   - Return affected records count
   - Show sample of affected records

8. **Action Chaining**
   - Execute multiple actions sequentially
   - Conditional execution based on previous results
   - Rollback on failure

9. **Advanced Undo with Snapshots**
   - Store full record state before modification
   - Selective field restoration
   - Bulk restore operations

### Phase 3 (Enterprise Features - Priority 3)

10. **Audit Trail**
    - Complete history of all bulk actions
    - User attribution
    - Searchable audit log

11. **Rate Limiting**
    - Prevent system overload
    - Configurable throttling
    - Queue priority management

12. **Export Integration**
    - Export results to CSV/Excel/PDF
    - Streaming exports for large datasets
    - Custom format support

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        User Interface Layer                  │
│  (Livewire Components, Inertia Components, API Endpoints)   │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                     BulkAction Facade/Class                  │
│              (Fluent API, Action Definition)                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────┐
│                   Action Executor Service                    │
│     (Validates, Authorizes, Queues, Tracks Progress)        │
└──────────────────────────┬──────────────────────────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼────────┐ ┌──────▼──────┐ ┌────────▼────────┐
│  Queue Jobs    │ │  Progress   │ │  Undo Manager   │
│  (Batched)     │ │  Tracker    │ │  (Snapshots)    │
└────────────────┘ └─────────────┘ └─────────────────┘
```

### Key Design Principles

1. **Framework Agnostic**: Core logic doesn't depend on Livewire, Inertia, or Filament
2. **Event-Driven**: Emit events at key points for extensibility
3. **Database Storage**: Track all actions in database for reliability
4. **Queue-First**: All bulk operations run asynchronously by default
5. **Testable**: Dependency injection, mockable components

---

## Directory Structure

```
src/
├── BulkActionsServiceProvider.php
├── Facades/
│   └── BulkAction.php
├── Actions/
│   ├── BulkActionBuilder.php          # Fluent API builder
│   ├── ActionExecutor.php             # Executes actions
│   ├── ActionRegistry.php             # Registers available actions
│   └── Concerns/
│       ├── HasAuthorization.php
│       ├── HasProgress.php
│       └── HasUndo.php
├── Jobs/
│   ├── ProcessBulkActionBatch.php     # Processes single batch
│   ├── CleanupExpiredUndo.php         # Cleanup job
│   └── ProcessScheduledAction.php     # For scheduled actions
├── Models/
│   ├── BulkActionExecution.php        # Tracks execution
│   ├── BulkActionProgress.php         # Progress tracking
│   └── BulkActionUndo.php             # Undo data storage
├── Traits/
│   └── HasBulkActions.php             # For Eloquent models
├── Http/
│   ├── Controllers/
│   │   ├── BulkActionController.php   # Execute actions
│   │   ├── ProgressController.php     # Progress polling
│   │   └── UndoController.php         # Undo operations
│   └── Middleware/
│       └── AuthorizeBulkAction.php
├── Events/
│   ├── BulkActionStarted.php
│   ├── BulkActionProgress.php
│   ├── BulkActionCompleted.php
│   ├── BulkActionFailed.php
│   └── BulkActionUndone.php
├── Exceptions/
│   ├── UnauthorizedBulkActionException.php
│   ├── InvalidBulkActionException.php
│   └── UndoExpiredException.php
├── Support/
│   ├── QueryBuilder.php               # Builds queries from filters
│   ├── ProgressTracker.php            # Tracks/calculates progress
│   └── UndoManager.php                # Manages undo operations
└── Console/
    └── Commands/
        └── CleanupBulkActionsCommand.php

config/
└── bulk-actions.php

database/
├── migrations/
│   ├── create_bulk_action_executions_table.php
│   ├── create_bulk_action_progress_table.php
│   └── create_bulk_action_undo_table.php
└── factories/
    └── BulkActionExecutionFactory.php

tests/
├── Feature/
│   ├── BulkActionExecutionTest.php
│   ├── ProgressTrackingTest.php
│   ├── UndoFunctionalityTest.php
│   └── AuthorizationTest.php
├── Unit/
│   ├── BulkActionBuilderTest.php
│   ├── ActionExecutorTest.php
│   └── QueryBuilderTest.php
└── TestCase.php

resources/
├── views/
│   └── livewire/
│       └── bulk-action-manager.blade.php  # Optional Livewire component
└── js/
    └── bulk-actions.js                     # Optional Alpine.js component

routes/
└── api.php                                 # Package routes

docs/
├── installation.md
├── basic-usage.md
├── advanced-features.md
└── examples/
    ├── livewire-integration.md
    ├── inertia-integration.md
    └── api-integration.md
```

---

## Database Schema

### `bulk_action_executions` Table

Stores metadata about each bulk action execution.

```php
Schema::create('bulk_action_executions', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('action_name');              // e.g., 'delete', 'archive', 'export'
    $table->string('model_type');               // Eloquent model class
    $table->json('filters')->nullable();        // Query filters applied
    $table->json('parameters')->nullable();     // Action-specific parameters
    $table->integer('total_records')->default(0);
    $table->integer('processed_records')->default(0);
    $table->integer('failed_records')->default(0);
    $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('scheduled_for')->nullable();  // For scheduled actions
    $table->json('error_details')->nullable();
    $table->boolean('can_undo')->default(false);
    $table->timestamp('undo_expires_at')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index(['status', 'created_at']);
    $table->index('user_id');
    $table->index('scheduled_for');
});
```

### `bulk_action_progress` Table

Tracks progress of individual batches within an execution.

```php
Schema::create('bulk_action_progress', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bulk_action_execution_id')->constrained()->cascadeOnDelete();
    $table->integer('batch_number');
    $table->integer('batch_size');
    $table->integer('processed_count')->default(0);
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
    $table->json('affected_ids')->nullable();    // IDs processed in this batch
    $table->text('error_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    
    $table->index('bulk_action_execution_id');
});
```

### `bulk_action_undo` Table

Stores data needed to undo actions.

```php
Schema::create('bulk_action_undo', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bulk_action_execution_id')->constrained()->cascadeOnDelete();
    $table->string('model_type');
    $table->unsignedBigInteger('model_id');
    $table->json('original_data')->nullable();   // Snapshot of original record
    $table->json('changes')->nullable();          // What was changed
    $table->enum('undo_action_type', ['restore', 'delete', 'update']); // How to undo
    $table->boolean('undone')->default(false);
    $table->timestamp('undone_at')->nullable();
    $table->timestamps();
    
    $table->index(['bulk_action_execution_id', 'undone']);
    $table->index(['model_type', 'model_id']);
});
```

---

## Core Components

### 1. BulkActionBuilder (Fluent API)

**Purpose:** Provide a clean, fluent interface for defining bulk actions.

**Key Methods:**

```php
namespace YourName\BulkActions\Actions;

class BulkActionBuilder
{
    protected string $modelClass;
    protected string $actionName;
    protected array $filters = [];
    protected array $parameters = [];
    protected ?int $batchSize = null;
    protected bool $shouldQueue = true;
    protected ?string $scheduledFor = null;
    protected bool $dryRun = false;
    protected bool $withUndo = false;
    protected ?int $undoExpiryDays = null;
    protected ?Closure $authorizationCallback = null;
    
    /**
     * Set the model class to perform action on
     */
    public function on(string $modelClass): self;
    
    /**
     * Define the action to perform
     */
    public function action(string $actionName): self;
    
    /**
     * Add WHERE conditions
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null): self;
    
    /**
     * Add whereIn condition
     */
    public function whereIn(string $column, array $values): self;
    
    /**
     * Specify exact IDs to target
     */
    public function ids(array $ids): self;
    
    /**
     * Add parameters for the action
     */
    public function with(array $parameters): self;
    
    /**
     * Set batch size
     */
    public function batchSize(int $size): self;
    
    /**
     * Run synchronously instead of queued
     */
    public function sync(): self;
    
    /**
     * Run in queue (default)
     */
    public function queue(): self;
    
    /**
     * Schedule for later execution
     */
    public function scheduleFor(string|Carbon $datetime): self;
    
    /**
     * Enable dry run mode
     */
    public function dryRun(): self;
    
    /**
     * Enable undo functionality
     */
    public function withUndo(int $expiryDays = 7): self;
    
    /**
     * Set custom authorization logic
     */
    public function authorize(Closure $callback): self;
    
    /**
     * Register progress callback
     */
    public function onProgress(Closure $callback): self;
    
    /**
     * Register completion callback
     */
    public function onComplete(Closure $callback): self;
    
    /**
     * Register failure callback
     */
    public function onFailure(Closure $callback): self;
    
    /**
     * Execute the bulk action
     */
    public function execute(): BulkActionExecution;
    
    /**
     * Get count of affected records without executing
     */
    public function count(): int;
    
    /**
     * Get sample of affected records
     */
    public function preview(int $limit = 10): Collection;
}
```

**Usage Example:**

```php
use YourName\BulkActions\Facades\BulkAction;

$execution = BulkAction::on(User::class)
    ->action('archive')
    ->where('last_login_at', '<', now()->subMonths(6))
    ->where('status', 'inactive')
    ->with(['archive_reason' => 'Inactivity'])
    ->batchSize(500)
    ->withUndo(days: 30)
    ->onProgress(fn($progress) => Log::info("Progress: {$progress}%"))
    ->execute();
```

### 2. ActionExecutor

**Purpose:** Core service that executes bulk actions.

**Responsibilities:**
- Validate action definition
- Check authorization
- Calculate total records
- Create execution record
- Dispatch batched jobs
- Handle errors

**Key Methods:**

```php
namespace YourName\BulkActions\Actions;

class ActionExecutor
{
    /**
     * Execute a bulk action from builder
     */
    public function execute(BulkActionBuilder $builder): BulkActionExecution;
    
    /**
     * Execute dry run
     */
    public function dryRun(BulkActionBuilder $builder): array;
    
    /**
     * Process a single batch
     */
    public function processBatch(
        BulkActionExecution $execution, 
        array $recordIds
    ): void;
    
    /**
     * Check if user is authorized for action
     */
    protected function authorize(BulkActionBuilder $builder): bool;
    
    /**
     * Build query from filters
     */
    protected function buildQuery(BulkActionBuilder $builder): Builder;
    
    /**
     * Chunk records into batches
     */
    protected function createBatches(Builder $query, int $batchSize): array;
    
    /**
     * Execute the actual action on a record
     */
    protected function executeActionOnRecord(
        Model $record, 
        string $actionName, 
        array $parameters
    ): void;
}
```

### 3. ActionRegistry

**Purpose:** Register and manage available actions.

**Built-in Actions:**
- `delete` - Soft delete or force delete
- `restore` - Restore soft deleted records
- `update` - Update specific fields
- `archive` - Custom archive logic
- `export` - Export to file

**Custom Action Registration:**

```php
namespace YourName\BulkActions\Actions;

class ActionRegistry
{
    protected array $actions = [];
    
    /**
     * Register a custom action
     */
    public function register(string $name, Closure|string $handler): void;
    
    /**
     * Get action handler
     */
    public function get(string $name): Closure|string;
    
    /**
     * Check if action exists
     */
    public function has(string $name): bool;
    
    /**
     * Get all registered actions
     */
    public function all(): array;
}
```

**Usage:**

```php
use YourName\BulkActions\Facades\ActionRegistry;

// In a service provider
ActionRegistry::register('send_email', function($record, $params) {
    Mail::to($record->email)->send(new BulkEmail($params['message']));
});

// Later use it
BulkAction::on(User::class)
    ->action('send_email')
    ->with(['message' => 'Special offer!'])
    ->execute();
```

### 4. ProgressTracker

**Purpose:** Track and calculate progress of bulk actions.

```php
namespace YourName\BulkActions\Support;

class ProgressTracker
{
    /**
     * Update progress for an execution
     */
    public function update(BulkActionExecution $execution, int $processedCount): void;
    
    /**
     * Get current progress percentage
     */
    public function getProgress(BulkActionExecution $execution): float;
    
    /**
     * Mark batch as completed
     */
    public function completeBatch(BulkActionProgress $progress): void;
    
    /**
     * Mark batch as failed
     */
    public function failBatch(BulkActionProgress $progress, string $error): void;
    
    /**
     * Get detailed progress data
     */
    public function getDetails(BulkActionExecution $execution): array;
    
    /**
     * Broadcast progress event
     */
    protected function broadcastProgress(BulkActionExecution $execution): void;
}
```

### 5. UndoManager

**Purpose:** Handle undo operations and snapshot storage.

```php
namespace YourName\BulkActions\Support;

class UndoManager
{
    /**
     * Store snapshot before action
     */
    public function captureSnapshot(
        BulkActionExecution $execution, 
        Model $record, 
        string $actionType
    ): void;
    
    /**
     * Undo an entire bulk action
     */
    public function undo(BulkActionExecution $execution): void;
    
    /**
     * Check if action can be undone
     */
    public function canUndo(BulkActionExecution $execution): bool;
    
    /**
     * Cleanup expired undo data
     */
    public function cleanup(): int;
    
    /**
     * Restore a single record
     */
    protected function restoreRecord(BulkActionUndo $undoRecord): void;
}
```

---

## API Design

### REST API Endpoints

```php
// routes/api.php

Route::middleware(['api', 'auth:sanctum'])->prefix('bulk-actions')->group(function () {
    
    // Execute bulk action
    Route::post('/', [BulkActionController::class, 'execute']);
    
    // Get execution details
    Route::get('/{uuid}', [BulkActionController::class, 'show']);
    
    // List user's bulk actions
    Route::get('/', [BulkActionController::class, 'index']);
    
    // Cancel pending/processing action
    Route::post('/{uuid}/cancel', [BulkActionController::class, 'cancel']);
    
    // Get progress
    Route::get('/{uuid}/progress', [ProgressController::class, 'show']);
    
    // Undo action
    Route::post('/{uuid}/undo', [UndoController::class, 'undo']);
    
    // Dry run preview
    Route::post('/preview', [BulkActionController::class, 'preview']);
});
```

### Request/Response Format

**Execute Action Request:**

```json
{
  "action": "archive",
  "model": "App\\Models\\User",
  "filters": {
    "where": [
      ["last_login_at", "<", "2024-06-01"],
      ["status", "=", "inactive"]
    ]
  },
  "parameters": {
    "archive_reason": "Inactivity"
  },
  "options": {
    "batch_size": 500,
    "with_undo": true,
    "undo_expiry_days": 30
  }
}
```

**Execute Action Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "action_name": "archive",
    "status": "pending",
    "total_records": 1250,
    "created_at": "2024-01-20T10:30:00Z",
    "can_undo": true,
    "undo_expires_at": "2024-02-19T10:30:00Z"
  }
}
```

**Progress Response:**

```json
{
  "success": true,
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "processing",
    "total_records": 1250,
    "processed_records": 750,
    "failed_records": 5,
    "progress_percentage": 60.4,
    "estimated_time_remaining": "2 minutes",
    "batches": {
      "total": 3,
      "completed": 2,
      "processing": 1,
      "failed": 0
    }
  }
}
```

---

## Integration Examples

### Livewire Component Example

```php
namespace App\Http\Livewire;

use Livewire\Component;
use YourName\BulkActions\Facades\BulkAction;
use YourName\BulkActions\Models\BulkActionExecution;

class UserBulkActions extends Component
{
    public array $selectedIds = [];
    public ?string $executionUuid = null;
    public ?BulkActionExecution $execution = null;
    
    protected $listeners = ['refreshProgress'];
    
    public function archiveSelected()
    {
        $execution = BulkAction::on(User::class)
            ->ids($this->selectedIds)
            ->action('archive')
            ->withUndo(days: 30)
            ->execute();
            
        $this->executionUuid = $execution->uuid;
        $this->startProgressPolling();
    }
    
    public function deleteSelected()
    {
        $this->validate([
            'selectedIds' => 'required|array|min:1',
        ]);
        
        $execution = BulkAction::on(User::class)
            ->ids($this->selectedIds)
            ->action('delete')
            ->execute();
            
        $this->executionUuid = $execution->uuid;
    }
    
    public function undoAction()
    {
        if ($this->execution && $this->execution->can_undo) {
            app(UndoManager::class)->undo($this->execution);
            $this->execution->refresh();
            session()->flash('message', 'Action undone successfully');
        }
    }
    
    public function refreshProgress()
    {
        if ($this->executionUuid) {
            $this->execution = BulkActionExecution::where('uuid', $this->executionUuid)->first();
        }
    }
    
    protected function startProgressPolling()
    {
        $this->dispatch('start-polling', uuid: $this->executionUuid);
    }
    
    public function render()
    {
        return view('livewire.user-bulk-actions');
    }
}
```

**Blade Template:**

```blade
<div>
    <div class="mb-4">
        <button wire:click="archiveSelected" 
                wire:loading.attr="disabled"
                class="btn btn-warning">
            Archive Selected ({{ count($selectedIds) }})
        </button>
        
        <button wire:click="deleteSelected" 
                wire:loading.attr="disabled"
                class="btn btn-danger">
            Delete Selected
        </button>
    </div>
    
    @if($execution)
        <div class="progress-card">
            <h3>{{ ucfirst($execution->action_name) }} Progress</h3>
            
            <div class="progress">
                <div class="progress-bar" 
                     style="width: {{ $execution->progress_percentage }}%">
                    {{ number_format($execution->progress_percentage, 1) }}%
                </div>
            </div>
            
            <p>
                Processed: {{ $execution->processed_records }} / {{ $execution->total_records }}
                @if($execution->failed_records > 0)
                    <span class="text-danger">({{ $execution->failed_records }} failed)</span>
                @endif
            </p>
            
            <p>Status: <span class="badge">{{ $execution->status }}</span></p>
            
            @if($execution->status === 'completed' && $execution->can_undo)
                <button wire:click="undoAction" class="btn btn-secondary">
                    Undo Action (expires {{ $execution->undo_expires_at->diffForHumans() }})
                </button>
            @endif
        </div>
    @endif
    
    <script>
        document.addEventListener('start-polling', event => {
            const uuid = event.detail.uuid;
            const interval = setInterval(() => {
                @this.call('refreshProgress');
                
                // Stop polling if completed or failed
                if (['completed', 'failed', 'cancelled'].includes(@this.execution?.status)) {
                    clearInterval(interval);
                }
            }, 2000); // Poll every 2 seconds
        });
    </script>
</div>
```

### Inertia.js Example

```typescript
// resources/js/Pages/Users/Index.vue

<script setup lang="ts">
import { ref, computed } from 'vue';
import axios from 'axios';

const selectedIds = ref<number[]>([]);
const execution = ref<any>(null);
let progressInterval: number | null = null;

async function bulkArchive() {
  const response = await axios.post('/api/bulk-actions', {
    action: 'archive',
    model: 'App\\Models\\User',
    filters: {
      where_in: [['id', selectedIds.value]]
    },
    options: {
      with_undo: true,
      undo_expiry_days: 30
    }
  });
  
  execution.value = response.data.data;
  startProgressPolling();
}

async function startProgressPolling() {
  progressInterval = setInterval(async () => {
    const response = await axios.get(`/api/bulk-actions/${execution.value.uuid}/progress`);
    execution.value = response.data.data;
    
    if (['completed', 'failed', 'cancelled'].includes(execution.value.status)) {
      stopProgressPolling();
    }
  }, 2000);
}

function stopProgressPolling() {
  if (progressInterval) {
    clearInterval(progressInterval);
    progressInterval = null;
  }
}

const progressPercentage = computed(() => {
  return execution.value ? execution.value.progress_percentage : 0;
});
</script>

<template>
  <div>
    <button @click="bulkArchive" :disabled="selectedIds.length === 0">
      Archive Selected ({{ selectedIds.length }})
    </button>
    
    <div v-if="execution" class="progress-card">
      <h3>{{ execution.action_name }} Progress</h3>
      <div class="progress-bar">
        <div :style="{ width: progressPercentage + '%' }">
          {{ progressPercentage.toFixed(1) }}%
        </div>
      </div>
    </div>
  </div>
</template>
```

### Plain API Usage (Vue/React/Angular)

```javascript
// Example with fetch API

class BulkActionService {
  async execute(action, model, filters, options = {}) {
    const response = await fetch('/api/bulk-actions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        action,
        model,
        filters,
        options
      })
    });
    
    return response.json();
  }
  
  async getProgress(uuid) {
    const response = await fetch(`/api/bulk-actions/${uuid}/progress`);
    return response.json();
  }
  
  async undo(uuid) {
    const response = await fetch(`/api/bulk-actions/${uuid}/undo`, {
      method: 'POST'
    });
    return response.json();
  }
  
  // Poll progress until complete
  pollProgress(uuid, callback, interval = 2000) {
    const poll = setInterval(async () => {
      const result = await this.getProgress(uuid);
      callback(result.data);
      
      if (['completed', 'failed', 'cancelled'].includes(result.data.status)) {
        clearInterval(poll);
      }
    }, interval);
    
    return () => clearInterval(poll); // Return cleanup function
  }
}
```

---

## Configuration

**config/bulk-actions.php**

```php
<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | Default Batch Size
    |--------------------------------------------------------------------------
    |
    | The default number of records to process in each queued batch.
    | Can be overridden per action.
    |
    */
    'batch_size' => env('BULK_ACTIONS_BATCH_SIZE', 500),
    
    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | The queue connection to use for bulk action jobs.
    |
    */
    'queue' => env('BULK_ACTIONS_QUEUE', 'default'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Undo Expiry
    |--------------------------------------------------------------------------
    |
    | Number of days before undo data is automatically cleaned up