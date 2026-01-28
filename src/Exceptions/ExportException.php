<?php

namespace DhruvilNagar\ActionEngine\Exceptions;

use Exception;

/**
 * ExportException
 * 
 * Thrown when export operations fail due to missing dependencies,
 * file system issues, or format-specific problems.
 * 
 * HTTP Status Code: 500 Internal Server Error
 */
class ExportException extends Exception
{
    /**
     * HTTP status code for export failures.
     */
    protected $code = 500;

    /**
     * Create exception for missing export driver.
     *
     * @param string $format The requested export format
     * @param string $requiredPackage The package required for this format
     * @return static
     */
    public static function driverNotFound(string $format, string $requiredPackage): static
    {
        return new static(
            "Export driver for format '{$format}' is not available. "
            . "Please install the required package: composer require {$requiredPackage}"
        );
    }

    /**
     * Create exception for unsupported format.
     *
     * @param string $format The unsupported format
     * @param array $supportedFormats List of supported formats
     * @return static
     */
    public static function unsupportedFormat(string $format, array $supportedFormats): static
    {
        $supported = implode(', ', $supportedFormats);
        
        return new static(
            "Export format '{$format}' is not supported. "
            . "Supported formats: {$supported}"
        );
    }

    /**
     * Create exception for file system error.
     *
     * @param string $operation The operation that failed (write, read, etc.)
     * @param string $path The file path
     * @param string|null $reason Additional details
     * @return static
     */
    public static function fileSystemError(string $operation, string $path, ?string $reason = null): static
    {
        $message = "Failed to {$operation} export file at '{$path}'.";
        
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        
        return new static($message);
    }

    /**
     * Create exception for export timeout.
     *
     * @param string $format The export format
     * @param int $recordCount Number of records being exported
     * @return static
     */
    public static function timeout(string $format, int $recordCount): static
    {
        return new static(
            "Export to {$format} timed out while processing {$recordCount} records. "
            . "Consider exporting fewer records or enabling streaming mode."
        );
    }

    /**
     * Create exception for data transformation error.
     *
     * @param string $format The target format
     * @param string $reason Why transformation failed
     * @return static
     */
    public static function transformationError(string $format, string $reason): static
    {
        return new static(
            "Failed to transform data for {$format} export: {$reason}"
        );
    }
}
