<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Backups\Application\Commands\SyncBackupsHandler;
use Modules\Backups\Application\Contracts\DatabaseBackupRunner;
use Modules\Backups\Domain\Enums\BackupStatus;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Runs one on-demand database backup off the request cycle. On success it
 * reconciles the index against the disk (which materialises the new archive as a
 * `Completed` row); on failure it writes a `Failed` row carrying the runner
 * output so the panel can surface what went wrong. Either way the outcome is
 * audited.
 */
final class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(
        DatabaseBackupRunner $runner,
        SyncBackupsHandler $sync,
        AuditPort $audit,
        Config $config,
    ): void {
        $startedAt = Carbon::now();
        $outcome = $runner->run();

        if ($outcome->successful()) {
            (void) $sync->handle();
            $audit->log(
                'backup.run_succeeded',
                null,
                ['output_tail' => Str::limit($outcome->output, 500)],
                null,
                'backups.backup',
            );

            return;
        }

        /** @var list<string> $disks */
        $disks = (array) $config->get('backup.backup.destination.disks', ['local']);

        BackupEloquentModel::query()->create([
            'disk' => (string) ($disks[0] ?? 'local'),
            'path' => null,
            'filename' => 'failed-'.$startedAt->format('Y-m-d-H-i-s'),
            'size_bytes' => null,
            'status' => BackupStatus::Failed,
            'connection' => (string) $config->get('database.default'),
            'error' => Str::limit($outcome->output, 2000),
            'started_at' => $startedAt,
            'finished_at' => Carbon::now(),
        ]);

        $audit->log('backup.run_failed', null, ['exit_code' => $outcome->exitCode], null, 'backups.backup');
    }
}
