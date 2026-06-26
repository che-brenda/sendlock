<?php

namespace App\Services\Ocr;

/**
 * Thin entry point the application calls for OCR. Delegates to the configured
 * {@see OcrDriver} (bound in AppServiceProvider from `sendlock.ocr.driver`) and
 * normalises the result. With the default null driver this is a no-op, so OCR
 * adds nothing to a scan until a real driver is configured.
 */
class OcrService
{
    public function __construct(private readonly OcrDriver $driver) {}

    /**
     * Extract text from a file. Returns an empty string when OCR is disabled or
     * finds nothing.
     */
    public function extract(string $absolutePath): string
    {
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return '';
        }

        return trim($this->driver->extract($absolutePath));
    }

    public function enabled(): bool
    {
        return ! $this->driver instanceof NullOcrDriver;
    }
}
