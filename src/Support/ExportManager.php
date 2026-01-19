<?php

namespace DhruvilNagar\ActionEngine\Support;

use DhruvilNagar\ActionEngine\Contracts\ExportDriverInterface;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ExportManager
{
    /**
     * Registered export drivers.
     */
    protected array $drivers = [];

    /**
     * Default driver.
     */
    protected string $defaultDriver = 'csv';

    public function __construct()
    {
        $this->registerDefaultDrivers();
    }

    /**
     * Register default export drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        $this->register('csv', new CsvExportDriver());
        
        // Register Excel driver if package is available
        if (class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            $this->register('xlsx', new ExcelExportDriver());
            $this->register('xls', new ExcelExportDriver());
        }

        // Register PDF driver if package is available
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $this->register('pdf', new PdfExportDriver());
        }
    }

    /**
     * Register a custom export driver.
     */
    public function register(string $format, ExportDriverInterface $driver): void
    {
        $this->drivers[$format] = $driver;
    }

    /**
     * Get an export driver.
     */
    public function driver(string $format): ExportDriverInterface
    {
        if (!isset($this->drivers[$format])) {
            throw new InvalidActionException("Export driver for format '{$format}' is not registered.");
        }

        return $this->drivers[$format];
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $format): bool
    {
        return isset($this->drivers[$format]);
    }

    /**
     * Get all available formats.
     */
    public function availableFormats(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Export data to a file.
     */
    public function export(
        Collection|array $data,
        string $format,
        string $filename,
        ?string $disk = null,
        array $options = []
    ): string {
        $driver = $this->driver($format);
        $disk = $disk ?? config('action-engine.export.disk', 'local');
        $directory = config('action-engine.export.directory', 'bulk-action-exports');
        
        // Ensure filename has the correct extension
        if (!str_ends_with($filename, ".{$format}")) {
            $filename .= ".{$format}";
        }

        $filePath = "{$directory}/{$filename}";

        // Convert to collection if array
        if (is_array($data)) {
            $data = collect($data);
        }

        // Generate the export file
        $content = $driver->generate($data, $options);

        // Store the file
        Storage::disk($disk)->put($filePath, $content);

        return $filePath;
    }

    /**
     * Export data and return download response.
     */
    public function download(
        Collection|array $data,
        string $format,
        string $filename,
        array $options = []
    ) {
        $driver = $this->driver($format);

        // Ensure filename has the correct extension
        if (!str_ends_with($filename, ".{$format}")) {
            $filename .= ".{$format}";
        }

        // Convert to collection if array
        if (is_array($data)) {
            $data = collect($data);
        }

        // Generate the export file
        $content = $driver->generate($data, $options);

        return $driver->download($content, $filename);
    }

    /**
     * Stream export data for large datasets.
     */
    public function stream(
        callable $dataCallback,
        string $format,
        string $filename,
        array $options = []
    ) {
        $driver = $this->driver($format);

        // Ensure filename has the correct extension
        if (!str_ends_with($filename, ".{$format}")) {
            $filename .= ".{$format}";
        }

        return $driver->stream($dataCallback, $filename, $options);
    }

    /**
     * Set the default driver.
     */
    public function setDefaultDriver(string $format): void
    {
        if (!$this->hasDriver($format)) {
            throw new InvalidActionException("Cannot set default driver to '{$format}'. Driver not registered.");
        }

        $this->defaultDriver = $format;
    }

    /**
     * Get the default driver.
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }
}

/**
 * CSV Export Driver
 */
class CsvExportDriver implements ExportDriverInterface
{
    public function generate(Collection $data, array $options = []): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';
        $includeHeaders = $options['include_headers'] ?? true;

        $output = fopen('php://temp', 'r+');

        // Write headers
        if ($includeHeaders) {
            $headers = array_keys($data->first());
            fputcsv($output, $headers, $delimiter, $enclosure, $escape);
        }

        // Write data rows
        foreach ($data as $row) {
            $rowData = [];
            foreach ($row as $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $rowData[] = $value;
            }
            fputcsv($output, $rowData, $delimiter, $enclosure, $escape);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    public function download(string $content, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function stream(callable $dataCallback, string $filename, array $options = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $escape = $options['escape'] ?? '\\';

        return response()->streamDownload(function () use ($dataCallback, $delimiter, $enclosure, $escape, $options) {
            $output = fopen('php://output', 'w');

            $headersWritten = false;
            $chunkSize = $options['chunk_size'] ?? 1000;

            $dataCallback(function ($chunk) use ($output, &$headersWritten, $delimiter, $enclosure, $escape, $options) {
                if (!$headersWritten && isset($options['include_headers']) && $options['include_headers']) {
                    if (!empty($chunk) && is_array($chunk) && isset($chunk[0])) {
                        $headers = array_keys($chunk[0]);
                        fputcsv($output, $headers, $delimiter, $enclosure, $escape);
                    }
                    $headersWritten = true;
                }

                foreach ($chunk as $row) {
                    $rowData = [];
                    foreach ($row as $value) {
                        if (is_array($value) || is_object($value)) {
                            $value = json_encode($value);
                        }
                        $rowData[] = $value;
                    }
                    fputcsv($output, $rowData, $delimiter, $enclosure, $escape);
                }
            }, $chunkSize);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

/**
 * Excel Export Driver (placeholder - requires maatwebsite/excel)
 */
class ExcelExportDriver implements ExportDriverInterface
{
    public function generate(Collection $data, array $options = []): string
    {
        throw new InvalidActionException('Excel export requires maatwebsite/excel package. Install it with: composer require maatwebsite/excel');
    }

    public function download(string $content, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        throw new InvalidActionException('Excel export requires maatwebsite/excel package. Install it with: composer require maatwebsite/excel');
    }

    public function stream(callable $dataCallback, string $filename, array $options = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        throw new InvalidActionException('Excel export requires maatwebsite/excel package. Install it with: composer require maatwebsite/excel');
    }
}

/**
 * PDF Export Driver (placeholder - requires barryvdh/laravel-dompdf)
 */
class PdfExportDriver implements ExportDriverInterface
{
    public function generate(Collection $data, array $options = []): string
    {
        throw new InvalidActionException('PDF export requires barryvdh/laravel-dompdf package. Install it with: composer require barryvdh/laravel-dompdf');
    }

    public function download(string $content, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        throw new InvalidActionException('PDF export requires barryvdh/laravel-dompdf package. Install it with: composer require barryvdh/laravel-dompdf');
    }

    public function stream(callable $dataCallback, string $filename, array $options = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        throw new InvalidActionException('PDF export requires barryvdh/laravel-dompdf package. Install it with: composer require barryvdh/laravel-dompdf');
    }
}
