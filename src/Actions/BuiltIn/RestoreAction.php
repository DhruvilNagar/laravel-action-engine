<?php

namespace DhruvilNagar\ActionEngine\Actions\BuiltIn;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoreAction implements ActionInterface
{
    public function execute(Model $record, array $parameters = []): bool
    {
        if (!$this->usesSoftDeletes($record)) {
            throw new InvalidActionException('Model does not support soft deletes.');
        }

        return $record->restore();
    }

    public function getName(): string
    {
        return 'restore';
    }

    public function getLabel(): string
    {
        return 'Restore';
    }

    public function supportsUndo(): bool
    {
        return true;
    }

    public function getUndoType(): ?string
    {
        return 'delete';
    }

    public function validateParameters(array $parameters): array
    {
        return [];
    }

    public function getUndoFields(): array
    {
        return ['deleted_at'];
    }

    public function afterComplete(array $results): void
    {
        // No cleanup needed
    }

    protected function usesSoftDeletes(Model $record): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($record));
    }
}
