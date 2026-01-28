<?php

namespace DhruvilNagar\ActionEngine\Support\ExportDrivers;

use DhruvilNagar\ActionEngine\Contracts\ExportDriverInterface;
use DhruvilNagar\ActionEngine\Exceptions\ExportException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * JsonStreamExportDriver
 * 
 * Export data to JSON format with streaming support for large datasets.
 * Handles memory-efficient export by processing data in chunks.
 */
class JsonStreamExportDriver implements ExportDriverInterface
{
    /**
     * Whether to pretty-print JSON.
     */
    protected bool $prettyPrint = false;

    /**
     * Whether to include metadata.
     */
    protected bool $includeMetadata = true;

    /**
     * JSON encoding options.
     */
    protected int $encodeOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Export data to JSON format.
     *
     * @param Collection $data The data to export
     * @param string $filename The output filename
     * @param array $options Export options
     * @return string The file path
     * @throws ExportException
     */
    public function export(Collection $data, string $filename, array $options = []): string
    {
        try {
            // Apply options
            $this->prettyPrint = $options['pretty_print'] ?? $this->prettyPrint;
            $this->includeMetadata = $options['include_metadata'] ?? $this->includeMetadata;

            if ($this->prettyPrint) {
                $this->encodeOptions |= JSON_PRETTY_PRINT;
            }

            $output = [];

            // Add metadata if enabled
            if ($this->includeMetadata && !empty($options['metadata'])) {
                $output['metadata'] = $options['metadata'];
            }

            // Add data
            $output['data'] = $data->toArray();

            // Add summary
            if (!empty($options['include_summary'])) {
                $output['summary'] = [
                    'total_records' => $data->count(),
                    'exported_at' => now()->toIso8601String(),
                ];
            }

            $json = json_encode($output, $this->encodeOptions);

            if ($json === false) {
                throw ExportException::transformationError('JSON', json_last_error_msg());
            }

            // Ensure filename has .json extension
            if (!str_ends_with($filename, '.json')) {
                $filename .= '.json';
            }

            // Store file
            $path = 'exports/' . $filename;
            Storage::put($path, $json);

            return $path;

        } catch (\Exception $e) {
            throw ExportException::fileSystemError('write', $filename, $e->getMessage());
        }
    }

    /**
     * Stream export for large datasets.
     *
     * @param \Closure $dataProvider Closure that yields data chunks
     * @param string $filename The output filename
     * @param array $options Export options
     * @return string The file path
     * @throws ExportException
     */
    public function streamExport(\Closure $dataProvider, string $filename, array $options = []): string
    {
        try {
            // Ensure filename has .json extension
            if (!str_ends_with($filename, '.json')) {
                $filename .= '.json';
            }

            $path = 'exports/' . $filename;
            $tempPath = storage_path('app/' . $path);

            // Ensure directory exists
            $directory = dirname($tempPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $handle = fopen($tempPath, 'w');

            if ($handle === false) {
                throw ExportException::fileSystemError('open', $tempPath);
            }

            // Start JSON structure
            fwrite($handle, '{');

            // Write metadata if provided
            if ($this->includeMetadata && !empty($options['metadata'])) {
                $metadata = json_encode($options['metadata'], $this->encodeOptions);
                fwrite($handle, '"metadata":' . $metadata . ',');
            }

            // Start data array
            fwrite($handle, '"data":[');

            $isFirstRecord = true;
            $totalRecords = 0;

            // Write records in chunks
            foreach ($dataProvider() as $chunk) {
                foreach ($chunk as $record) {
                    if (!$isFirstRecord) {
                        fwrite($handle, ',');
                    }

                    $recordJson = json_encode($record, $this->encodeOptions);

                    if ($recordJson === false) {
                        throw ExportException::transformationError('JSON', json_last_error_msg());
                    }

                    fwrite($handle, $recordJson);
                    $isFirstRecord = false;
                    $totalRecords++;
                }

                // Free memory
                unset($chunk);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Close data array
            fwrite($handle, ']');

            // Add summary if requested
            if (!empty($options['include_summary'])) {
                $summary = json_encode([
                    'total_records' => $totalRecords,
                    'exported_at' => now()->toIso8601String(),
                ], $this->encodeOptions);
                fwrite($handle, ',"summary":' . $summary);
            }

            // Close JSON structure
            fwrite($handle, '}');

            fclose($handle);

            return $path;

        } catch (\Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }

            throw ExportException::fileSystemError('stream', $filename, $e->getMessage());
        }
    }

    /**
     * Export as JSON Lines format (newline-delimited JSON).
     *
     * @param \Closure $dataProvider Closure that yields data chunks
     * @param string $filename The output filename
     * @param array $options Export options
     * @return string The file path
     * @throws ExportException
     */
    public function exportJsonLines(\Closure $dataProvider, string $filename, array $options = []): string
    {
        try {
            // Ensure filename has .jsonl or .ndjson extension
            if (!str_ends_with($filename, '.jsonl') && !str_ends_with($filename, '.ndjson')) {
                $filename .= '.jsonl';
            }

            $path = 'exports/' . $filename;
            $tempPath = storage_path('app/' . $path);

            // Ensure directory exists
            $directory = dirname($tempPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $handle = fopen($tempPath, 'w');

            if ($handle === false) {
                throw ExportException::fileSystemError('open', $tempPath);
            }

            // Write records in chunks, one JSON object per line
            foreach ($dataProvider() as $chunk) {
                foreach ($chunk as $record) {
                    $recordJson = json_encode($record, $this->encodeOptions);

                    if ($recordJson === false) {
                        throw ExportException::transformationError('JSON Lines', json_last_error_msg());
                    }

                    fwrite($handle, $recordJson . "\n");
                }

                // Free memory
                unset($chunk);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            fclose($handle);

            return $path;

        } catch (\Exception $e) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }

            throw ExportException::fileSystemError('stream', $filename, $e->getMessage());
        }
    }

    /**
     * Validate JSON structure before writing.
     */
    protected function validateJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if this driver supports the given format.
     */
    public function supports(string $format): bool
    {
        return in_array(strtolower($format), ['json', 'jsonl', 'ndjson', 'json-stream']);
    }

    /**
     * Get the MIME type for this export format.
     */
    public function getMimeType(): string
    {
        return 'application/json';
    }

    /**
     * Get the file extension for this export format.
     */
    public function getExtension(): string
    {
        return 'json';
    }

    /**
     * Get memory-efficient export recommendations.
     */
    public function getMemoryRecommendations(int $recordCount, int $avgRecordSize): array
    {
        $estimatedMemory = $recordCount * $avgRecordSize * 2; // Factor for JSON overhead

        return [
            'estimated_memory' => $estimatedMemory,
            'recommended_chunk_size' => max(100, (int) (10 * 1024 * 1024 / $avgRecordSize)), // 10MB chunks
            'use_streaming' => $estimatedMemory > 50 * 1024 * 1024, // Use streaming for >50MB
            'use_json_lines' => $recordCount > 100000, // Use JSONL for very large datasets
        ];
    }
}
