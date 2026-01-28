<?php

namespace DhruvilNagar\ActionEngine\Tests\Unit;

use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use DhruvilNagar\ActionEngine\Exceptions\RateLimitExceededException;
use DhruvilNagar\ActionEngine\Exceptions\UnauthorizedBulkActionException;
use DhruvilNagar\ActionEngine\Exceptions\ActionExecutionException;
use DhruvilNagar\ActionEngine\Exceptions\ExportException;
use DhruvilNagar\ActionEngine\Exceptions\QueueException;
use DhruvilNagar\ActionEngine\Tests\TestCase;

/**
 * ExceptionHandlingTest
 * 
 * Tests all exception classes for proper behavior and error messages.
 */
class ExceptionHandlingTest extends TestCase
{
    /** @test */
    public function it_creates_invalid_action_not_registered_exception()
    {
        $exception = InvalidActionException::notRegistered('custom-action');

        $this->assertInstanceOf(InvalidActionException::class, $exception);
        $this->assertStringContainsString('custom-action', $exception->getMessage());
        $this->assertStringContainsString('not registered', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    /** @test */
    public function it_creates_invalid_action_configuration_exception()
    {
        $exception = InvalidActionException::invalidConfiguration('my-action', 'Missing required parameter');

        $this->assertInstanceOf(InvalidActionException::class, $exception);
        $this->assertStringContainsString('my-action', $exception->getMessage());
        $this->assertStringContainsString('Missing required parameter', $exception->getMessage());
    }

    /** @test */
    public function it_creates_rate_limit_exceeded_exception()
    {
        $exception = RateLimitExceededException::tooManyConcurrent(5, 3);

        $this->assertInstanceOf(RateLimitExceededException::class, $exception);
        $this->assertStringContainsString('maximum', $exception->getMessage());
        $this->assertStringContainsString('5', $exception->getMessage());
        $this->assertStringContainsString('3', $exception->getMessage());
        $this->assertEquals(429, $exception->getCode());
    }

    /** @test */
    public function it_creates_unauthorized_exception()
    {
        $exception = UnauthorizedBulkActionException::missingPermission('delete', 'bulk.delete');

        $this->assertInstanceOf(UnauthorizedBulkActionException::class, $exception);
        $this->assertStringContainsString('delete', $exception->getMessage());
        $this->assertStringContainsString('bulk.delete', $exception->getMessage());
        $this->assertEquals(403, $exception->getCode());
    }

    /** @test */
    public function it_creates_unauthorized_policy_denied_exception()
    {
        $exception = UnauthorizedBulkActionException::policyDenied('update', 'App\Models\Product');

        $this->assertInstanceOf(UnauthorizedBulkActionException::class, $exception);
        $this->assertStringContainsString('update', $exception->getMessage());
        $this->assertStringContainsString('Product', $exception->getMessage());
    }

    /** @test */
    public function it_creates_action_execution_record_failed_exception()
    {
        $previous = new \Exception('Database error');
        $exception = ActionExecutionException::recordFailed(123, 'update', $previous);

        $this->assertInstanceOf(ActionExecutionException::class, $exception);
        $this->assertStringContainsString('123', $exception->getMessage());
        $this->assertStringContainsString('update', $exception->getMessage());
        $this->assertStringContainsString('Database error', $exception->getMessage());
        $this->assertEquals(123, $exception->getRecordId());
        $this->assertEquals(500, $exception->getCode());
    }

    /** @test */
    public function it_creates_action_execution_batch_failed_exception()
    {
        $exception = ActionExecutionException::batchFailed(5, 'delete', 10);

        $this->assertInstanceOf(ActionExecutionException::class, $exception);
        $this->assertStringContainsString('Batch #5', $exception->getMessage());
        $this->assertStringContainsString('delete', $exception->getMessage());
        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertEquals(5, $exception->getBatchNumber());
    }

    /** @test */
    public function it_creates_constraint_violation_exception()
    {
        $exception = ActionExecutionException::constraintViolation('foreign_key_constraint', 'delete');

        $this->assertInstanceOf(ActionExecutionException::class, $exception);
        $this->assertStringContainsString('constraint violation', $exception->getMessage());
        $this->assertStringContainsString('foreign_key_constraint', $exception->getMessage());
        $this->assertStringContainsString('foreign key', $exception->getMessage());
    }

    /** @test */
    public function it_creates_timeout_exception()
    {
        $exception = ActionExecutionException::timeout('bulk-update', 300);

        $this->assertInstanceOf(ActionExecutionException::class, $exception);
        $this->assertStringContainsString('exceeded', $exception->getMessage());
        $this->assertStringContainsString('300 seconds', $exception->getMessage());
    }

    /** @test */
    public function it_creates_memory_exceeded_exception()
    {
        $exception = ActionExecutionException::memoryExceeded('bulk-update', '512M');

        $this->assertInstanceOf(ActionExecutionException::class, $exception);
        $this->assertStringContainsString('memory limit', $exception->getMessage());
        $this->assertStringContainsString('512M', $exception->getMessage());
    }

    /** @test */
    public function it_identifies_retryable_errors()
    {
        $timeoutException = ActionExecutionException::timeout('action', 60);
        $this->assertTrue($timeoutException->isRetryable());

        $constraintException = ActionExecutionException::constraintViolation('fk', 'delete');
        $this->assertFalse($constraintException->isRetryable());
    }

    /** @test */
    public function it_creates_export_driver_not_found_exception()
    {
        $exception = ExportException::driverNotFound('excel', 'maatwebsite/excel');

        $this->assertInstanceOf(ExportException::class, $exception);
        $this->assertStringContainsString('excel', $exception->getMessage());
        $this->assertStringContainsString('maatwebsite/excel', $exception->getMessage());
        $this->assertEquals(500, $exception->getCode());
    }

    /** @test */
    public function it_creates_export_unsupported_format_exception()
    {
        $exception = ExportException::unsupportedFormat('xml', ['csv', 'xlsx', 'pdf']);

        $this->assertInstanceOf(ExportException::class, $exception);
        $this->assertStringContainsString('xml', $exception->getMessage());
        $this->assertStringContainsString('csv', $exception->getMessage());
        $this->assertStringContainsString('xlsx', $exception->getMessage());
    }

    /** @test */
    public function it_creates_export_file_system_error_exception()
    {
        $exception = ExportException::fileSystemError('write', '/path/to/file.csv', 'Permission denied');

        $this->assertInstanceOf(ExportException::class, $exception);
        $this->assertStringContainsString('write', $exception->getMessage());
        $this->assertStringContainsString('/path/to/file.csv', $exception->getMessage());
        $this->assertStringContainsString('Permission denied', $exception->getMessage());
    }

    /** @test */
    public function it_creates_export_timeout_exception()
    {
        $exception = ExportException::timeout('csv', 100000);

        $this->assertInstanceOf(ExportException::class, $exception);
        $this->assertStringContainsString('timed out', $exception->getMessage());
        $this->assertStringContainsString('100000', $exception->getMessage());
    }

    /** @test */
    public function it_creates_queue_connection_failed_exception()
    {
        $exception = QueueException::connectionFailed('redis', 'Connection refused');

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('redis', $exception->getMessage());
        $this->assertStringContainsString('Connection refused', $exception->getMessage());
        $this->assertEquals(503, $exception->getCode());
    }

    /** @test */
    public function it_creates_queue_dispatch_failed_exception()
    {
        $exception = QueueException::dispatchFailed('ProcessBulkAction', 'Queue full');

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('ProcessBulkAction', $exception->getMessage());
        $this->assertStringContainsString('Queue full', $exception->getMessage());
    }

    /** @test */
    public function it_creates_no_workers_running_exception()
    {
        $exception = QueueException::noWorkersRunning('bulk-actions');

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('No queue workers', $exception->getMessage());
        $this->assertStringContainsString('bulk-actions', $exception->getMessage());
        $this->assertStringContainsString('queue:work', $exception->getMessage());
    }

    /** @test */
    public function it_creates_max_attempts_exceeded_exception()
    {
        $exception = QueueException::maxAttemptsExceeded('update-products', 5);

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('update-products', $exception->getMessage());
        $this->assertStringContainsString('5 times', $exception->getMessage());
        $this->assertStringContainsString('exceeded', $exception->getMessage());
    }

    /** @test */
    public function it_creates_queue_timeout_exception()
    {
        $exception = QueueException::timeout('bulk-delete', 3600);

        $this->assertInstanceOf(QueueException::class, $exception);
        $this->assertStringContainsString('timed out', $exception->getMessage());
        $this->assertStringContainsString('3600', $exception->getMessage());
    }

    /** @test */
    public function all_exceptions_have_proper_http_codes()
    {
        $exceptions = [
            InvalidActionException::notRegistered('test') => 400,
            RateLimitExceededException::tooManyConcurrent(5, 3) => 429,
            UnauthorizedBulkActionException::missingPermission('test') => 403,
            ActionExecutionException::recordFailed(1, 'test') => 500,
            ExportException::driverNotFound('test', 'package') => 500,
            QueueException::connectionFailed('test') => 503,
        ];

        foreach ($exceptions as $exception => $expectedCode) {
            $this->assertEquals($expectedCode, $exception->getCode());
        }
    }

    /** @test */
    public function exceptions_can_chain_previous_exceptions()
    {
        $previous = new \Exception('Original error');
        $exception = ActionExecutionException::recordFailed(1, 'test', $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('Original error', $exception->getPrevious()->getMessage());
    }
}
