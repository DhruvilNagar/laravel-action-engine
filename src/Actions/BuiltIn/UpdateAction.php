<?php

namespace DhruvilNagar\ActionEngine\Actions\BuiltIn;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use Illuminate\Database\Eloquent\Model;

class UpdateAction implements ActionInterface
{
    public function execute(Model $record, array $parameters = []): bool
    {
        $data = $parameters['data'] ?? $parameters;
        
        // Remove meta parameters
        unset($data['data']);

        if (empty($data)) {
            throw new InvalidActionException('No data provided for update.');
        }

        return $record->update($data);
    }

    public function getName(): string
    {
        return 'update';
    }

    public function getLabel(): string
    {
        return 'Update';
    }

    public function supportsUndo(): bool
    {
        return true;
    }

    public function getUndoType(): ?string
    {
        return 'update';
    }

    public function validateParameters(array $parameters): array
    {
        $data = $parameters['data'] ?? $parameters;
        unset($data['data']);

        if (empty($data)) {
            throw new InvalidActionException('Update action requires data to update.');
        }

        return ['data' => $data];
    }

    public function getUndoFields(): array
    {
        return ['*'];
    }

    public function afterComplete(array $results): void
    {
        // No cleanup needed
    }
}
