<?php

namespace DhruvilNagar\ActionEngine\Actions\BuiltIn;

use DhruvilNagar\ActionEngine\Contracts\ActionInterface;
use DhruvilNagar\ActionEngine\Exceptions\InvalidActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExportAction implements ActionInterface
{
    /**
     * Collected records for batch export.
     */
    protected static array $exportBuffer = [];

    /**
     * Current export ID.
     */
    protected static ?string $currentExportId = null;

    public function execute(Model $record, array $parameters = []): bool
    {
        $exportId = $parameters['export_id'] ?? uniqid('export_');
        $columns = $parameters['columns'] ?? ['*'];

        // Initialize buffer for this export
        if (self::$currentExportId !== $exportId) {
            self::$exportBuffer = [];
            self::$currentExportId = $exportId;
        }

        // Get data from record
        $data = $columns === ['*'] 
            ? $record->toArray() 
            : $record->only($columns);

        self::$exportBuffer[] = $data;

        return true;
    }

    public function getName(): string
    {
        return 'export';
    }

    public function getLabel(): string
    {
        return 'Export';
    }

    public function supportsUndo(): bool
    {
        return false;
    }

    public function getUndoType(): ?string
    {
        return null;
    }

    public function validateParameters(array $parameters): array
    {
        $format = $parameters['format'] ?? 'csv';
        $validFormats = config('action-engine.export.formats', ['csv', 'xlsx', 'pdf']);

        if (!in_array($format, $validFormats)) {
            throw new InvalidActionException("Invalid export format: {$format}");
        }

        return [
            'format' => $format,
            'columns' => $parameters['columns'] ?? ['*'],
            'filename' => $parameters['filename'] ?? null,
            'export_id' => $parameters['export_id'] ?? uniqid('export_'),
        ];
    }

    public function getUndoFields(): array
    {
        return [];
    }

    public function afterComplete(array $results): void
    {
        if (empty(self::$exportBuffer)) {
            return;
        }

        $format = $results['format'] ?? 'csv';
        $filename = $results['filename'] ?? 'export_' . date('Y-m-d_His');
        $disk = config('action-engine.export.disk', 'local');
        $directory = config('action-engine.export.directory', 'bulk-action-exports');

        $filePath = "{$directory}/{$filename}.{$format}";

        // Generate the export file
        $this->generateExportFile($format, $filePath, $disk);

        // Clear buffer
        self::$exportBuffer = [];
        self::$currentExportId = null;
    }

    protected function generateExportFile(string $format, string $filePath, string $disk): void
    {
        switch ($format) {
            case 'csv':
                $this->generateCsv($filePath, $disk);
                break;
            case 'xlsx':
                $this->generateXlsx($filePath, $disk);
                break;
            case 'pdf':
                $this->generatePdf($filePath, $disk);
                break;
        }
    }

    protected function generateCsv(string $filePath, string $disk): void
    {
        if (empty(self::$exportBuffer)) {
            return;
        }

        $headers = array_keys(self::$exportBuffer[0]);
        $csv = implode(',', $headers) . "\n";

        foreach (self::$exportBuffer as $row) {
            $csv .= implode(',', array_map(function ($value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                return '"' . str_replace('"', '""', (string) $value) . '"';
            }, $row)) . "\n";
        }

        Storage::disk($disk)->put($filePath, $csv);
    }

    protected function generateXlsx(string $filePath, string $disk): void
    {
        // This would use maatwebsite/excel if available
        // Fallback to CSV if not
        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            $this->generateCsv(str_replace('.xlsx', '.csv', $filePath), $disk);
            return;
        }

        // Implementation with maatwebsite/excel would go here
        $this->generateCsv(str_replace('.xlsx', '.csv', $filePath), $disk);
    }

    protected function generatePdf(string $filePath, string $disk): void
    {
        // This would use barryvdh/laravel-dompdf if available
        // For now, we'll skip PDF generation
        throw new InvalidActionException('PDF export requires barryvdh/laravel-dompdf package.');
    }

    /**
     * Get the generated file path.
     */
    public static function getExportPath(): ?string
    {
        return self::$currentExportId;
    }

    /**
     * Clear the export buffer.
     */
    public static function clearBuffer(): void
    {
        self::$exportBuffer = [];
        self::$currentExportId = null;
    }
}
