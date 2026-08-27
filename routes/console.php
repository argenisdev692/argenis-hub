<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;
use Spatie\OneTimePasswords\Models\OneTimePassword;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Authentication & audit retention (spec 001 FR-17, clarify Q5)
|--------------------------------------------------------------------------
|
| Activity-log rows past the 90-day hot window are streamed to R2 cold storage
| as gzipped NDJSON and then purged from the table by `activity-log:archive`
| (Modules\ActivityLog). Session tracking rows and expired one-time codes are
| pruned on the same nightly pass: neither is evidence, and holding
| IP/user-agent pairs longer than the feature needs is data collection without
| a purpose.
|
*/

Schedule::command('activity-log:archive')->dailyAt('03:00');

Schedule::command('model:prune', [
    '--model' => [
        AuthSessionEloquentModel::class,
        OneTimePassword::class,
    ],
])->dailyAt('03:15');
