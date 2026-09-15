<?php

use App\Models\UsageEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/**
 * Delete usage events past their retention window, once a day.
 *
 * The model is named explicitly so the prune can never reach another model, and
 * `conversion_metrics` in particular: aggregates are durable and expire never.
 */
Schedule::command('model:prune', ['--model' => [UsageEvent::class]])->daily();
