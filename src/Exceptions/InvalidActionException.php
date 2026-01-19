<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * InvalidActionException
 * 
 * Thrown when attempting to execute or retrieve an unregistered action,
 * or when an action is malformed or improperly configured.
 * 
 * This exception indicates a developer error (action not registered)
 * rather than a user input error.
 * 
 * HTTP Status Code: 400 Bad Request
 */
class InvalidActionException extends Exception
{
    /**
     * HTTP status code for this exception.
     */
    protected $code = 400;

    /**
     * Create a new exception for an unregistered action.
     *
     * @param string $actionName The action name that was not found
     * @return static
     */
    public static function notRegistered(string $actionName): static
    {
        return new static(
            "Action '{$actionName}' is not registered. "
            . "Please register it using ActionRegistry::register() or check for typos."
        );
    }

    /**
     * Create a new exception for invalid action configuration.
     *
     * @param string $actionName The action name with invalid configuration
     * @param string $reason Explanation of what's invalid
     * @return static
     */
    public static function invalidConfiguration(string $actionName, string $reason): static
    {
        return new static(
            "Action '{$actionName}' has invalid configuration: {$reason}"
        );
    }
}
