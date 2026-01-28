<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * QueueException
 * 
 * Thrown when queue-related operations fail, such as dispatching jobs,
 * connecting to queue services, or processing failures.
 * 
 * HTTP Status Code: 503 Service Unavailable
 */
class QueueException extends Exception
{
    /**
     * HTTP status code for queue issues.
     */
    protected $code = 503;

    /**
     * Create exception for queue connection failure.
     *
     * @param string $connection The connection name that failed
     * @param string|null $reason Additional details
     * @return static
     */
    public static function connectionFailed(string $connection, ?string $reason = null): static
    {
        $message = "Failed to connect to queue '{$connection}'.";
        
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        $message .= " Please ensure queue workers are running and the queue service is available.";
        
        return new static($message);
    }

    /**
     * Create exception for job dispatch failure.
     *
     * @param string $jobClass The job class that failed to dispatch
     * @param string|null $reason Why dispatch failed
     * @return static
     */
    public static function dispatchFailed(string $jobClass, ?string $reason = null): static
    {
        $message = "Failed to dispatch job '{$jobClass}' to queue.";
        
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        return new static($message);
    }

    /**
     * Create exception for no workers running.
     *
     * @param string $queue The queue name
     * @return static
     */
    public static function noWorkersRunning(string $queue): static
    {
        return new static(
            "No queue workers are running for queue '{$queue}'. "
            . "Please start workers using: php artisan queue:work --queue={$queue}"
        );
    }

    /**
     * Create exception for max attempts exceeded.
     *
     * @param string $actionName The action that exceeded attempts
     * @param int $attempts Number of attempts made
     * @return static
     */
    public static function maxAttemptsExceeded(string $actionName, int $attempts): static
    {
        return new static(
            "Action '{$actionName}' has failed {$attempts} times and exceeded maximum retry attempts. "
            . "Check the failed jobs queue for more details: php artisan queue:failed"
        );
    }

    /**
     * Create exception for queue timeout.
     *
     * @param string $actionName The action that timed out in queue
     * @param int $timeoutSeconds The timeout limit
     * @return static
     */
    public static function timeout(string $actionName, int $timeoutSeconds): static
    {
        return new static(
            "Queued action '{$actionName}' timed out after {$timeoutSeconds} seconds. "
            . "Consider increasing the job timeout or processing fewer records."
        );
    }
}
