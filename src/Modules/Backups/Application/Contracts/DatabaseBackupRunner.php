<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Contracts;

/**
 * Port over "produce a fresh database archive". The production adapter shells
 * out to `spatie/laravel-backup` (`backup:run --only-db`); tests bind a fake
 * that returns a deterministic {@see BackupRunOutcome} without touching the
 * filesystem or a database dumper.
 */
interface DatabaseBackupRunner
{
    #[\NoDiscard]
    public function run(): BackupRunOutcome;
}
