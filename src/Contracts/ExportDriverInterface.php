<?php

namespace DhruvilNagar\ActionEngine\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface for export driver implementations.
 */
interface ExportDriverInterface
{
    /**
     * Get the file extension for this export format.
     */
    public function getExtension(): string;

    /**
     * Get the MIME type for this export format.
     */
    public function getMimeType(): string;

    /**
     * Export data from a query to a file.
     *
     * @param Builder $query
     * @param string $filePath
     * @param array $columns Columns to export
     * @param array $options Additional options
     * @return string The path to the generated file
     */
    public function export(Builder $query, string $filePath, array $columns, array $options = []): string;

    /**
     * Stream export (for large datasets).
     *
     * @param Builder $query
     * @param array $columns
     * @param array $options
     * @return \Generator
     */
    public function stream(Builder $query, array $columns, array $options = []): \Generator;

    /**
     * Get the display name of this export format.
     */
    public function getLabel(): string;
}
