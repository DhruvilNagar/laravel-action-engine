<?php

namespace Dhruvil\ActionEngine\Tests\Feature;

use Dhruvil\ActionEngine\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class MigrationTest extends TestCase
{
    /**
     * Test that all migrations run successfully.
     *
     * @return void
     */
    public function test_migrations_run_successfully()
    {
        // Run migrations
        Artisan::call('migrate', ['--force' => true]);
        
        // Assert that all expected tables exist
        $this->assertTrue(Schema::hasTable('bulk_action_executions'));
        $this->assertTrue(Schema::hasTable('bulk_action_progress'));
        $this->assertTrue(Schema::hasTable('bulk_action_undo'));
        $this->assertTrue(Schema::hasTable('bulk_action_audit'));
    }

    /**
     * Test that bulk_action_executions table has expected columns.
     *
     * @return void
     */
    public function test_bulk_action_executions_table_structure()
    {
        Artisan::call('migrate', ['--force' => true]);
        
        $expectedColumns = [
            'id',
            'uuid',
            'action_name',
            'model_type',
            'filters',
            'parameters',
            'total_records',
            'processed_records',
            'failed_records',
            'status',
            'user_id',
            'user_type',
            'started_at',
            'completed_at',
            'scheduled_for',
            'scheduled_timezone',
            'error_details',
            'can_undo',
            'undo_expires_at',
            'is_dry_run',
            'dry_run_results',
            'batch_size',
            'queue_connection',
            'queue_name',
            'callbacks',
            'chain_config',
            'parent_execution_uuid',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('bulk_action_executions', $column),
                "Column '{$column}' is missing from bulk_action_executions table"
            );
        }
    }

    /**
     * Test that improvement migration adds expected columns.
     *
     * @return void
     */
    public function test_improvement_migration_adds_columns()
    {
        Artisan::call('migrate', ['--force' => true]);
        
        // Check that improvement migration columns exist
        $this->assertTrue(
            Schema::hasColumn('bulk_action_executions', 'is_locked'),
            "Column 'is_locked' was not added by improvement migration"
        );
        
        $this->assertTrue(
            Schema::hasColumn('bulk_action_executions', 'rolled_back_at'),
            "Column 'rolled_back_at' was not added by improvement migration"
        );
        
        $this->assertTrue(
            Schema::hasColumn('bulk_action_executions', 'retry_count'),
            "Column 'retry_count' was not added by improvement migration"
        );
        
        $this->assertTrue(
            Schema::hasColumn('bulk_action_undo', 'is_compressed'),
            "Column 'is_compressed' was not added by improvement migration"
        );
    }

    /**
     * Test that migrations can be rolled back successfully.
     *
     * @return void
     */
    public function test_migrations_can_be_rolled_back()
    {
        // Run migrations
        Artisan::call('migrate', ['--force' => true]);
        
        // Rollback migrations
        Artisan::call('migrate:rollback', ['--force' => true]);
        
        // Assert that tables were dropped
        $this->assertFalse(Schema::hasTable('bulk_action_executions'));
        $this->assertFalse(Schema::hasTable('bulk_action_progress'));
        $this->assertFalse(Schema::hasTable('bulk_action_undo'));
        $this->assertFalse(Schema::hasTable('bulk_action_audit'));
    }

    /**
     * Test that migrations can be run multiple times (idempotent).
     *
     * @return void
     */
    public function test_migrations_are_idempotent()
    {
        // Run migrations twice
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('migrate', ['--force' => true]);
        
        // Tables should still exist and be functional
        $this->assertTrue(Schema::hasTable('bulk_action_executions'));
        $this->assertTrue(Schema::hasTable('bulk_action_progress'));
        $this->assertTrue(Schema::hasTable('bulk_action_undo'));
        $this->assertTrue(Schema::hasTable('bulk_action_audit'));
    }

    /**
     * Test that expected indexes exist after migrations.
     *
     * @return void
     */
    public function test_expected_indexes_exist()
    {
        Artisan::call('migrate', ['--force' => true]);
        
        $connection = Schema::getConnection();
        $schemaManager = $connection->getDoctrineSchemaManager();
        
        // Get indexes for bulk_action_executions table
        $indexes = $schemaManager->listTableIndexes('bulk_action_executions');
        $indexNames = array_keys($indexes);
        
        // Check for base indexes
        $this->assertContains('bulk_action_executions_uuid_unique', $indexNames);
        
        // Note: Index names may vary by database driver, so we check for existence generally
        $this->assertNotEmpty($indexes, 'Table should have indexes');
    }

    /**
     * Test that foreign keys are properly set up.
     *
     * @return void
     */
    public function test_foreign_keys_are_set_up()
    {
        Artisan::call('migrate', ['--force' => true]);
        
        $connection = Schema::getConnection();
        $schemaManager = $connection->getDoctrineSchemaManager();
        
        // Get foreign keys for bulk_action_progress table
        $foreignKeys = $schemaManager->listTableForeignKeys('bulk_action_progress');
        
        $this->assertNotEmpty($foreignKeys, 'bulk_action_progress should have foreign key constraints');
        
        // Get foreign keys for bulk_action_undo table
        $foreignKeys = $schemaManager->listTableForeignKeys('bulk_action_undo');
        
        $this->assertNotEmpty($foreignKeys, 'bulk_action_undo should have foreign key constraints');
    }
}
