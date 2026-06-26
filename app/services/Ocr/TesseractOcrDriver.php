<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Process;

/**
 * OCR via the self-hosted Tesseract binary (free, no external service). Shells
 * out to `tesseract <file> stdout` and returns the recognised text.
 *
 * Degrades to an empty string on any failure (binary missing, unreadable file,
 * non-zero exit) so a scan is never broken by OCR. Requires the `tesseract`
 * binary on PATH — see README / CLAUDE.md.
 */
class TesseractOcrDriver implements OcrDriver
{
    public function __construct(
        private readonly string $binary = 'tesseract',
        private readonly int $timeout = 30,
    ) {}

    public function extract(string $absolutePath): string
    {
        if (! is_readable($absolutePath)) {
            return '';
        }

        try {
            $result = Process::timeout($this->timeout)
                ->run([$this->binary, $absolutePath, 'stdout']);
        } catch (\Throwable $e) {
            return '';
        }

        if (! $result->successful()) {
            return '';
        }

        return trim($result->output());
    }
}
