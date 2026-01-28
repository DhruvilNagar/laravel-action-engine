<?php

namespace DhruvilNagar\ActionEngine\Support\ExportDrivers;

use DhruvilNagar\ActionEngine\Contracts\ExportDriverInterface;
use DhruvilNagar\ActionEngine\Exceptions\ExportException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * XmlExportDriver
 * 
 * Export data to XML format with proper structure and encoding.
 */
class XmlExportDriver implements ExportDriverInterface
{
    /**
     * The root element name.
     */
    protected string $rootElement = 'data';

    /**
     * The row element name.
     */
    protected string $rowElement = 'record';

    /**
     * XML version.
     */
    protected string $version = '1.0';

    /**
     * Character encoding.
     */
    protected string $encoding = 'UTF-8';

    /**
     * Whether to format the output with indentation.
     */
    protected bool $formatOutput = true;

    /**
     * Export data to XML format.
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
            $this->rootElement = $options['root_element'] ?? $this->rootElement;
            $this->rowElement = $options['row_element'] ?? $this->rowElement;
            $this->formatOutput = $options['format_output'] ?? $this->formatOutput;

            // Create XML document
            $xml = new \DOMDocument($this->version, $this->encoding);
            $xml->formatOutput = $this->formatOutput;

            // Create root element
            $root = $xml->createElement($this->rootElement);
            $xml->appendChild($root);

            // Add metadata if provided
            if (!empty($options['metadata'])) {
                $this->addMetadata($xml, $root, $options['metadata']);
            }

            // Add records
            foreach ($data as $record) {
                $this->addRecord($xml, $root, $record);
            }

            // Ensure filename has .xml extension
            if (!str_ends_with($filename, '.xml')) {
                $filename .= '.xml';
            }

            // Save to file
            $content = $xml->saveXML();

            if ($content === false) {
                throw ExportException::transformationError('XML', 'Failed to generate XML content');
            }

            // Store file
            $path = 'exports/' . $filename;
            Storage::put($path, $content);

            return $path;

        } catch (\DOMException $e) {
            throw ExportException::transformationError('XML', $e->getMessage());
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
            // Ensure filename has .xml extension
            if (!str_ends_with($filename, '.xml')) {
                $filename .= '.xml';
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

            // Write XML header and root opening tag
            fwrite($handle, "<?xml version=\"{$this->version}\" encoding=\"{$this->encoding}\"?>\n");
            fwrite($handle, "<{$this->rootElement}>\n");

            // Write records in chunks
            foreach ($dataProvider() as $chunk) {
                foreach ($chunk as $record) {
                    $recordXml = $this->recordToXml($record);
                    fwrite($handle, $recordXml . "\n");
                }
            }

            // Write closing tag
            fwrite($handle, "</{$this->rootElement}>\n");

            fclose($handle);

            return $path;

        } catch (\Exception $e) {
            throw ExportException::fileSystemError('stream', $filename, $e->getMessage());
        }
    }

    /**
     * Add metadata section to XML.
     */
    protected function addMetadata(\DOMDocument $xml, \DOMElement $root, array $metadata): void
    {
        $metadataElement = $xml->createElement('metadata');
        $root->appendChild($metadataElement);

        foreach ($metadata as $key => $value) {
            $element = $xml->createElement($this->sanitizeTagName($key), $this->sanitizeValue($value));
            $metadataElement->appendChild($element);
        }
    }

    /**
     * Add a record to the XML document.
     */
    protected function addRecord(\DOMDocument $xml, \DOMElement $root, mixed $record): void
    {
        $recordElement = $xml->createElement($this->rowElement);
        $root->appendChild($recordElement);

        // Convert record to array
        $data = is_array($record) ? $record : (array) $record;

        foreach ($data as $key => $value) {
            $this->addElement($xml, $recordElement, $key, $value);
        }
    }

    /**
     * Add an element to the parent.
     */
    protected function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, mixed $value): void
    {
        $name = $this->sanitizeTagName($name);

        if (is_array($value)) {
            // Create nested element for arrays
            $element = $xml->createElement($name);
            $parent->appendChild($element);

            foreach ($value as $subKey => $subValue) {
                $this->addElement($xml, $element, (string) $subKey, $subValue);
            }
        } elseif (is_object($value)) {
            // Convert object to array
            $this->addElement($xml, $parent, $name, (array) $value);
        } else {
            // Create simple element
            $element = $xml->createElement($name, $this->sanitizeValue($value));
            $parent->appendChild($element);
        }
    }

    /**
     * Convert a record to XML string (for streaming).
     */
    protected function recordToXml(mixed $record): string
    {
        $data = is_array($record) ? $record : (array) $record;

        $xml = "  <{$this->rowElement}>";

        foreach ($data as $key => $value) {
            $key = $this->sanitizeTagName($key);
            $value = $this->sanitizeValue($value);
            $xml .= "<{$key}>{$value}</{$key}>";
        }

        $xml .= "</{$this->rowElement}>";

        return $xml;
    }

    /**
     * Sanitize tag name to be XML-compliant.
     */
    protected function sanitizeTagName(string $name): string
    {
        // XML tag names must start with letter or underscore
        // Can only contain letters, digits, hyphens, underscores, and periods
        $name = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $name);

        // Ensure it starts with a letter or underscore
        if (!preg_match('/^[a-zA-Z_]/', $name)) {
            $name = '_' . $name;
        }

        return $name;
    }

    /**
     * Sanitize value for XML content.
     */
    protected function sanitizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        // Escape special XML characters
        return htmlspecialchars((string) $value, ENT_XML1, $this->encoding);
    }

    /**
     * Check if this driver supports the given format.
     */
    public function supports(string $format): bool
    {
        return strtolower($format) === 'xml';
    }

    /**
     * Get the MIME type for this export format.
     */
    public function getMimeType(): string
    {
        return 'application/xml';
    }

    /**
     * Get the file extension for this export format.
     */
    public function getExtension(): string
    {
        return 'xml';
    }
}
