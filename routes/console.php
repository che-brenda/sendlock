<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh the bulk phishing-list cache. No-ops unless SENDLOCK_THREAT_LISTS is
// set, so this is safe to schedule unconditionally — it only fetches when opted in.
Schedule::command('sendlock:import-threat-feeds')->hourly()->withoutOverlapping();
