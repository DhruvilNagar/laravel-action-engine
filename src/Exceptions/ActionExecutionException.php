<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;
use Throwable;

/**
 * ActionExecutionException
 * 
 * Thrown when an action fails during execution due to runtime errors,
 * database issues, or business logic failures.
 * 
 * This exception wraps underlying exceptions and provides context
 * about which record/batch failed during bulk operations.
 * 
 * HTTP Status Code: 500 Internal Server Error
 */
class ActionExecutionException extends Exception
{
    /**
     * HTTP status code for execution failures.
     */
    protected $code = 500;

    /**
     * The record ID that caused the failure (if applicable).
     */
    protected mixed $recordId = null;

    /**
     * The batch number where failure occurred.
     */
    protected ?int $batchNumber = null;

    /**
     * Create exception for record-level failure.
     *
     * @param mixed $recordId The ID of the record that failed
     * @param string $actionName The action that was being executed
     * @param Throwable|null $previous The underlying exception
     * @return static
     */
    public static function recordFailed(mixed $recordId, string $actionName, ?Throwable $previous = null): static
    {
        $exception = new static(
            "Failed to execute action '{$actionName}' on record ID {$recordId}: " 
            . ($previous ? $previous->getMessage() : 'Unknown error'),
            0,
            $previous
        );
        
        $exception->recordId = $recordId;
        
        return $exception;
    }

    /**
     * Create exception for batch failure.
     *
     * @param int $batchNumber The batch number that failed
     * @param string $actionName The action that was being executed
     * @param int $failedCount Number of records that failed in batch
     * @param Throwable|null $previous The underlying exception
     * @return static
     */
    public static function batchFailed(
        int $batchNumber,
        string $actionName,
        int $failedCount,
        ?Throwable $previous = null
    ): static {
        $exception = new static(
            "Batch #{$batchNumber} failed while executing '{$actionName}': "
            . "{$failedCount} record(s) could not be processed. "
            . ($previous ? $previous->getMessage() : ''),
            0,
            $previous
        );
        
        $exception->batchNumber = $batchNumber;
        
        return $exception;
    }

    /**
     * Create exception for database constraint violation.
     *
     * @param string $constraint The constraint that was violated
     * @param string $actionName The action being executed
     * @param Throwable|null $previous The underlying database exception
     * @return static
     */
    public static function constraintViolation(
        string $constraint,
        string $actionName,
        ?Throwable $previous = null
    ): static {
        return new static(
            "Database constraint violation during '{$actionName}': {$constraint}. "
            . "This may indicate foreign key relationships or unique constraints. "
            . "Please check your data integrity.",
            0,
            $previous
        );
    }

    /**
     * Create exception for timeout.
     *
     * @param string $actionName The action that timed out
     * @param int $timeoutSeconds The timeout limit
     * @return static
     */
    public static function timeout(string $actionName, int $timeoutSeconds): static
    {
        return new static(
            "Action '{$actionName}' exceeded maximum execution time of {$timeoutSeconds} seconds. "
            . "Consider processing fewer records or increasing the timeout limit."
        );
    }

    /**
     * Create exception for memory limit.
     *
     * @param string $actionName The action that exceeded memory
     * @param string $memoryLimit The memory limit that was exceeded
     * @return static
     */
    public static function memoryExceeded(string $actionName, string $memoryLimit): static
    {
        return new static(
            "Action '{$actionName}' exceeded memory limit of {$memoryLimit}. "
            . "Try reducing batch size or processing fewer records at once."
        );
    }

    /**
     * Get the record ID that caused the failure.
     */
    public function getRecordId(): mixed
    {
        return $this->recordId;
    }

    /**
     * Get the batch number where failure occurred.
     */
    public function getBatchNumber(): ?int
    {
        return $this->batchNumber;
    }

    /**
     * Check if this is a retryable error.
     */
    public function isRetryable(): bool
    {
        // Timeouts and temporary database issues are retryable
        return str_contains($this->getMessage(), 'timeout') 
            || str_contains($this->getMessage(), 'lock') 
            || str_contains($this->getMessage(), 'deadlock')
            || str_contains($this->getMessage(), 'connection');
    }
}
