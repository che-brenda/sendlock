<?php

namespace App\Services\Ocr;

/**
 * The default, safe OCR driver: extracts nothing. Keeps the platform fully
 * functional (and the test suite offline) with no binary installed. Swap to
 * {@see TesseractOcrDriver} via SENDLOCK_OCR_DRIVER=tesseract to enable OCR.
 */
class NullOcrDriver implements OcrDriver
{
    public function extract(string $absolutePath): string
    {
        return '';
    }
}
