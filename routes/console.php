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
| Authentication retention (spec 001 FR-17, clarify Q5)
|--------------------------------------------------------------------------
|
| The audit trail is kept for 12 months (config/activitylog.php →
| clean_after_days), then trimmed. Session tracking rows and expired one-time
| codes are pruned on the same nightly pass: neither is evidence, and holding
| IP/user-agent pairs longer than the feature needs is data collection without
| a purpose.
|
*/

Schedule::command('activitylog:clean')->dailyAt('03:00');

Schedule::command('model:prune', [
    '--model' => [
        AuthSessionEloquentModel::class,
        OneTimePassword::class,
    ],
])->dailyAt('03:15');
