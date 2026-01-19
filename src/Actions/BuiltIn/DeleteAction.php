<?php

namespace DhruvilNagar\ActionEngine\Actions\BuiltIn;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeleteAction implements ActionInterface
{
    public function execute(Model $record, array $parameters = []): bool
    {
        $forceDelete = $parameters['force'] ?? false;

        if ($forceDelete || !$this->usesSoftDeletes($record)) {
            return $record->forceDelete();
        }

        return $record->delete();
    }

    public function getName(): string
    {
        return 'delete';
    }

    public function getLabel(): string
    {
        return 'Delete';
    }

    public function supportsUndo(): bool
    {
        return true;
    }

    public function getUndoType(): ?string
    {
        return 'restore';
    }

    public function validateParameters(array $parameters): array
    {
        return [
            'force' => (bool) ($parameters['force'] ?? false),
        ];
    }

    public function getUndoFields(): array
    {
        return ['*'];
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
