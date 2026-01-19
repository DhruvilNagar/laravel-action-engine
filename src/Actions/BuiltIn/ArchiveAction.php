<?php

namespace DhruvilNagar\ActionEngine\Actions\BuiltIn;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use Illuminate\Database\Eloquent\Model;

class ArchiveAction implements ActionInterface
{
    /**
     * Default archive column name.
     */
    protected string $archiveColumn = 'archived_at';

    /**
     * Default archive reason column.
     */
    protected string $reasonColumn = 'archive_reason';

    public function execute(Model $record, array $parameters = []): bool
    {
        $archiveColumn = $parameters['archive_column'] ?? $this->archiveColumn;
        $reasonColumn = $parameters['reason_column'] ?? $this->reasonColumn;
        $reason = $parameters['reason'] ?? $parameters['archive_reason'] ?? null;

        $data = [
            $archiveColumn => now(),
        ];

        // Add reason if the column exists and reason is provided
        if ($reason && $this->hasColumn($record, $reasonColumn)) {
            $data[$reasonColumn] = $reason;
        }

        return $record->update($data);
    }

    public function getName(): string
    {
        return 'archive';
    }

    public function getLabel(): string
    {
        return 'Archive';
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
        return [
            'archive_column' => $parameters['archive_column'] ?? $this->archiveColumn,
            'reason_column' => $parameters['reason_column'] ?? $this->reasonColumn,
            'reason' => $parameters['reason'] ?? $parameters['archive_reason'] ?? null,
        ];
    }

    public function getUndoFields(): array
    {
        return [$this->archiveColumn, $this->reasonColumn];
    }

    public function afterComplete(array $results): void
    {
        // No cleanup needed
    }

    protected function hasColumn(Model $record, string $column): bool
    {
        return in_array($column, $record->getFillable()) ||
               $record->getConnection()->getSchemaBuilder()->hasColumn($record->getTable(), $column);
    }
}
