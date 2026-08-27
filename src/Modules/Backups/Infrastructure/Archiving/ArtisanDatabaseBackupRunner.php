<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Archiving;

use Illuminate\Contracts\Console\Kernel as Artisan;
use Modules\Backups\Application\Contracts\BackupRunOutcome;
use Modules\Backups\Application\Contracts\DatabaseBackupRunner;

/**
 * Production adapter: delegates to `spatie/laravel-backup`'s `backup:run`
 * command, scoped to the database only and with the package's own mail/Slack
 * notifications disabled (this module records outcomes itself).
 */
final readonly class ArtisanDatabaseBackupRunner implements DatabaseBackupRunner
{
    public function __construct(private Artisan $artisan) {}

    public function run(): BackupRunOutcome
    {
        $exitCode = $this->artisan->call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        return new BackupRunOutcome($exitCode, $this->artisan->output());
    }
}
