<?php

use App\Models\ErrorReport;
use App\Models\UsageEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Delete usage events and error reports past their retention windows, once a day.
 *
 * The models are named explicitly so the prune can never reach another model, and
 * `conversion_metrics` in particular: aggregates are durable and expire never.
 */
Schedule::command('model:prune', ['--model' => [UsageEvent::class, ErrorReport::class]])->daily();
