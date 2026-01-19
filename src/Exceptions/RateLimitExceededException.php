<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * RateLimitExceededException
 * 
 * Thrown when a user exceeds rate limits for bulk action execution.
 * 
 * Rate limits can be exceeded in two ways:
 * 1. Too many concurrent active actions
 * 2. Action initiated during cooldown period
 * 
 * This exception should be caught and presented with a user-friendly
 * message indicating when they can retry.
 * 
 * HTTP Status Code: 429 Too Many Requests
 */
class RateLimitExceededException extends Exception
{
    /**
     * HTTP status code for rate limit violations.
     */
    protected $code = 429;

    /**
     * When the user can retry (timestamp).
     */
    protected ?int $retryAfter = null;

    /**
     * Create exception for concurrent action limit.
     *
     * @param int $currentCount Current number of active actions
     * @param int $maxAllowed Maximum allowed concurrent actions
     * @return static
     */
    public static function tooManyConcurrent(int $currentCount, int $maxAllowed): static
    {
        return new static(
            "You have reached the maximum number of concurrent bulk actions ({$maxAllowed}). "
            . "You currently have {$currentCount} active actions. Please wait for some to complete before starting new ones."
        );
    }

    /**
     * Create exception for cooldown period.
     *
     * @param int $secondsRemaining Seconds until cooldown expires
     * @return static
     */
    public static function inCooldown(int $secondsRemaining): static
    {
        $exception = new static(
            "You must wait {$secondsRemaining} seconds before initiating another bulk action."
        );
        $exception->retryAfter = time() + $secondsRemaining;
        return $exception;
    }

    /**
     * Get the timestamp when retry is allowed.
     *
     * @return int|null Unix timestamp or null if not set
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
