<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Auth\Infrastructure\Persistence\Eloquent\Models\AuthSessionEloquentModel;
use Modules\Backups\Infrastructure\Console\Commands\SyncBackupsCommand;
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

/*
|--------------------------------------------------------------------------
| Database backups (Modules\Backups · spatie/laravel-backup, database-only)
|--------------------------------------------------------------------------
|
| `backup:clean` prunes old archives per the retention strategy, `backup:run
| --only-db` writes a fresh gzip dump to the configured disk (R2), `backups:sync`
| reconciles the `backups` index table with what is now on disk, and
| `backup:monitor` fires the unhealthy-backup notification if the newest archive
| is too old or the destination is oversized.
|
*/

/*
|--------------------------------------------------------------------------
| Scheduled social media publishing (Modules\SocialMedia)
|--------------------------------------------------------------------------
|
| `social-media:publish-scheduled` flips every package whose `scheduled_at`
| has been reached from `scheduled` to `published`. Runs every minute so a
| package scheduled for 14:30 goes out at 14:30, not on the next hourly tick;
| the command is a no-op query when nothing is due, and
| `withoutOverlapping()` stops a slow tick from stacking with the next one.
|
*/

Schedule::command('social-media:publish-scheduled')->everyMinute()->withoutOverlapping();

Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command(SyncBackupsCommand::class)->dailyAt('02:30');
Schedule::command('backup:monitor')->dailyAt('03:00');
