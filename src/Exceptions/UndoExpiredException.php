<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * UndoExpiredException
 * 
 * Thrown when attempting to undo a bulk action after the undo period has expired.
 * 
 * The undo expiration time is configured per-execution and typically ranges
 * from 1-90 days depending on system configuration and the specific action.
 * 
 * Once expired, the original data snapshots may be purged from the system
 * for data retention compliance.
 * 
 * HTTP Status Code: 410 Gone (resource existed but is no longer available)
 */
class UndoExpiredException extends Exception
{
    /**
     * HTTP status code indicating resource is gone.
     */
    protected $code = 410;

    /**
     * Create a new undo expired exception.
     *
     * @param string $message The exception message
     */
    public function __construct(string $message = 'The undo period for this action has expired.')
    {
        parent::__construct($message);
    }

    /**
     * Create exception with expiration details.
     *
     * @param \Carbon\Carbon $expiredAt When the undo period expired
     * @return static
     */
    public static function withExpiration($expiredAt): static
    {
        $formatted = $expiredAt->diffForHumans();
        return new static(
            "The undo period for this action expired {$formatted}. The action cannot be reversed."
        );
    }

    /**
     * Create exception for already undone action.
     *
     * @return static
     */
    public static function alreadyUndone(): static
    {
        return new static(
            "This action has already been undone and cannot be undone again."
        );
    }
}
