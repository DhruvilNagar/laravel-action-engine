<?php

namespace DhruvilNagar\ActionEngine\Traits;

use DhruvilNagar\ActionEngine\Support\SafetyManager;

trait HasSafetyFeatures
{
    protected SafetyManager $safetyManager;

    /**
     * Initialize safety features
     */
    public function initializeSafetyFeatures(): void
    {
        $this->safetyManager = app(SafetyManager::class);
    }

    /**
     * Require confirmation before executing
     */
    public function requireConfirmation(): self
    {
        $this->requiresConfirmation = true;
        return $this;
    }

    /**
     * Enable soft delete before hard delete
     */
    public function withSoftDelete(): self
    {
        $this->useSoftDelete = true;
        return $this;
    }

    /**
     * Enable record locking during processing
     */
    public function withRecordLocking(): self
    {
        $this->enableRecordLocking = true;
        return $this;
    }

    /**
     * Enable automatic rollback on partial failure
     */
    public function withAutoRollback(): self
    {
        $this->enableAutoRollback = true;
        return $this;
    }

    /**
     * Require dry run for first-time destructive operations
     */
    public function requireDryRunFirstTime(): self
    {
        $this->requireDryRunFirst = true;
        return $this;
    }

    /**
     * Get confirmation prompt data
     */
    public function getConfirmationData(): array
    {
        if (!$this->requiresConfirmation) {
            return [];
        }

        return $this->safetyManager->getConfirmationPrompt(
            $this->actionType,
            $this->getRecordCount()
        );
    }

    /**
     * Validate user confirmation
     */
    public function validateConfirmation(string $input): bool
    {
        return $this->safetyManager->validateConfirmation($this->actionType, $input);
    }

    /**
     * Get the total number of records to be processed
     */
    abstract protected function getRecordCount(): int;
}
