<?php

namespace App\Providers;

use App\Services\Ocr\NullOcrDriver;
use App\Services\Ocr\OcrDriver;
use App\Services\Ocr\TesseractOcrDriver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the OCR driver from config. Defaults to the null (no-op) driver
        // so nothing runs until SENDLOCK_OCR_DRIVER=tesseract + the binary are set.
        $this->app->bind(OcrDriver::class, function () {
            return match (config('sendlock.ocr.driver', 'null')) {
                'tesseract' => new TesseractOcrDriver(
                    (string) config('sendlock.ocr.tesseract_binary', 'tesseract'),
                    (int) config('sendlock.ocr.timeout', 30),
                ),
                default => new NullOcrDriver,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
