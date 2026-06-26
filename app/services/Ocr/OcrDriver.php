<?php

namespace App\Services\Ocr;

/**
 * Extracts text from an image or scanned-document file. Implementations must be
 * side-effect-free and resilient: any failure should yield an empty string
 * rather than throwing, so a bad upload never breaks a scan.
 */
interface OcrDriver
{
    /**
     * Return the text recognised in the file at the given absolute path, or an
     * empty string when nothing could be extracted.
     */
    public function extract(string $absolutePath): string;
}
