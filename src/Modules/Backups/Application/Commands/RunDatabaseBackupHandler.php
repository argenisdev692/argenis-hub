<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Commands;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Backups\Infrastructure\Queue\RunDatabaseBackupJob;
use Shared\Domain\Ports\AuditPort;

/**
 * Entry point for an on-demand database backup. Records who asked for it, then
 * hands the slow work (dump + upload + reconcile) to a queued job so the request
 * returns immediately.
 */
final readonly class RunDatabaseBackupHandler
{
    public function __construct(private AuditPort $audit) {}

    public function handle(?Authenticatable $causer = null): void
    {
        $this->audit->log('backup.run_requested', null, [], $causer, 'backups.backup');

        RunDatabaseBackupJob::dispatch();
    }
}
