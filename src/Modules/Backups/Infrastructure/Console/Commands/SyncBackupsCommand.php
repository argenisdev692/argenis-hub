<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\Backups\Application\Commands\SyncBackupsHandler;
use Modules\Backups\Providers\BackupsServiceProvider;

/**
 * Reconciles the `backups` index with the archives on the backup disk. Scheduled
 * in `routes/console.php` right after `backup:run` / `backup:clean` so the panel
 * reflects the disk within the same nightly window. Registered by
 * {@see BackupsServiceProvider}.
 */
final class SyncBackupsCommand extends Command
{
    protected $signature = 'backups:sync';

    protected $description = 'Reconcile the backups table with the archives present on the backup disk.';

    public function handle(SyncBackupsHandler $sync): int
    {
        $count = $sync->handle();

        $this->info("Synced {$count} backup archive(s) from disk.");

        return self::SUCCESS;
    }
}
