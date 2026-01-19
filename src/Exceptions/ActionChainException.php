<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * ActionChainException
 * 
 * Thrown when a chained bulk action sequence fails at any step.
 * 
 * Captures context about which action in the chain failed and at what
 * step number, enabling better error reporting and potential retry logic.
 * 
 * When a chain fails, previous actions may need to be rolled back
 * depending on transaction configuration.
 * 
 * HTTP Status Code: 500 Internal Server Error
 */
class ActionChainException extends Exception
{
    /**
     * HTTP status code for chain execution failures.
     */
    protected $code = 500;

    /**
     * The name of the action that failed in the chain.
     */
    protected ?string $failedAction = null;

    /**
     * The step number (1-indexed) where the chain failed.
     */
    protected ?int $failedStep = null;

    /**
     * Set context about which action and step failed.
     *
     * @param string $action The action name that failed
     * @param int $step The step number (1-indexed) in the chain
     * @return $this
     */
    public function setFailedAction(string $action, int $step): self
    {
        $this->failedAction = $action;
        $this->failedStep = $step;
        return $this;
    }

    /**
     * Get the action name that failed.
     *
     * @return string|null
     */
    public function getFailedAction(): ?string
    {
        return $this->failedAction;
    }

    /**
     * Get the step number where the chain failed.
     *
     * @return int|null
     */
    public function getFailedStep(): ?int
    {
        return $this->failedStep;
    }

    /**
     * Create exception for chain failure with context.
     *
     * @param string $action The action that failed
     * @param int $step The step number
     * @param \Throwable $previous The underlying exception
     * @return static
     */
    public static function atStep(string $action, int $step, \Throwable $previous): static
    {
        $exception = new static(
            "Action chain failed at step {$step} (action: {$action}): {$previous->getMessage()}",
            0,
            $previous
        );
        $exception->setFailedAction($action, $step);
        return $exception;
    }
}
