<?php

namespace App\Providers;

use App\Services\Ai\ClaudeContentClassifier;
use App\Services\Ai\ContentClassifier;
use App\Services\Ai\GeminiContentClassifier;
use App\Services\Ai\NullContentClassifier;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\LiveDnsResolver;
use App\Services\Dns\NullDnsResolver;
use App\Services\DomainAge\DomainAgeResolver;
use App\Services\DomainAge\NullDomainAgeResolver;
use App\Services\DomainAge\RdapDomainAgeResolver;
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

        // Resolve the AI content classifier from config. Defaults to the null
        // (no-op) classifier so nothing calls out until SENDLOCK_AI_DRIVER + the
        // matching API key are set. Gemini (beta) and Claude (production) share
        // the ContentClassifier contract — promotion is a driver swap.
        $this->app->bind(ContentClassifier::class, function () {
            return match (config('sendlock.ai.driver', 'null')) {
                'gemini' => new GeminiContentClassifier,
                'claude' => new ClaudeContentClassifier,
                default => new NullContentClassifier,
            };
        });

        // MX/DNS resolver. Defaults to live (built-in PHP DNS, no key); tests pin
        // it to null so the suite never touches the network.
        $this->app->bind(DnsResolver::class, function () {
            return match (config('sendlock.dns.driver', 'live')) {
                'live' => new LiveDnsResolver,
                default => new NullDnsResolver,
            };
        });

        // Domain-age resolver. Defaults to null (unknown) — inert until an RDAP
        // provider is enabled via SENDLOCK_DOMAIN_AGE_DRIVER=rdap.
        $this->app->bind(DomainAgeResolver::class, function () {
            return match (config('sendlock.domain_age.driver', 'null')) {
                'rdap' => new RdapDomainAgeResolver,
                default => new NullDomainAgeResolver,
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
