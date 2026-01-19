<?php

namespace DhruvilNagar\ActionEngine\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Interface for bulk action handlers.
 */
interface ActionInterface
{
    /**
     * Execute the action on a single record.
     *
     * @param Model $record The record to perform the action on
     * @param array $parameters Action-specific parameters
     * @return bool Whether the action was successful
     */
    public function execute(Model $record, array $parameters = []): bool;

    /**
     * Get the name of the action.
     */
    public function getName(): string;

    /**
     * Get the display label for the action.
     */
    public function getLabel(): string;

    /**
     * Check if this action supports undo functionality.
     */
    public function supportsUndo(): bool;

    /**
     * Get the undo action type for this action.
     * 
     * @return string|null One of: 'restore', 'delete', 'update', or null if not undoable
     */
    public function getUndoType(): ?string;

    /**
     * Validate the action parameters.
     *
     * @param array $parameters
     * @return array Validated parameters
     * @throws \DhruvilNagar\ActionEngine\Exceptions\InvalidActionException
     */
    public function validateParameters(array $parameters): array;

    /**
     * Get the fields that should be captured for undo.
     * Return ['*'] to capture all fields, or specify field names.
     *
     * @return array
     */
    public function getUndoFields(): array;

    /**
     * Perform any cleanup after the action completes.
     *
     * @param array $results Summary of the action results
     */
    public function afterComplete(array $results): void;
}
