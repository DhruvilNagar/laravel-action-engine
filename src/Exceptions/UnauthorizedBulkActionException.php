<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * UnauthorizedBulkActionException
 * 
 * Thrown when a user attempts to execute a bulk action without proper authorization.
 * 
 * This exception is triggered by failed Gate checks or explicit permission denials.
 * It should be caught and presented to users with an appropriate access denied message.
 * 
 * HTTP Status Code: 403 Forbidden
 */
class UnauthorizedBulkActionException extends Exception
{
    /**
     * HTTP status code for authorization failures.
     */
    protected $code = 403;

    /**
     * Create exception for missing permission.
     *
     * @param string $actionName The action that was denied
     * @param string|null $permission The specific permission that was checked
     * @return static
     */
    public static function missingPermission(string $actionName, ?string $permission = null): static
    {
        $message = "You do not have permission to execute the '{$actionName}' action.";
        
        if ($permission) {
            $message .= " Required permission: {$permission}";
        }

        return new static($message);
    }

    /**
     * Create exception for policy denial.
     *
     * @param string $actionName The action that was denied
     * @param string $modelClass The model class the policy applies to
     * @return static
     */
    public static function policyDenied(string $actionName, string $modelClass): static
    {
        return new static(
            "Policy denied execution of '{$actionName}' action on {$modelClass}."
        );
    }
}
