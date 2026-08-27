<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Commands;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Hard-deletes the selected backups (archive file + index row) over a UUID
 * array. There is no `BulkRestoreBackupHandler` counterpart on purpose: a
 * deleted archive is physically removed from storage, so the usual
 * soft-delete / soft-restore pairing does not apply here.
 */
final readonly class BulkDeleteBackupHandler
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private AuditPort $audit,
    ) {}

    /**
     * @param  list<string>  $uuids
     */
    #[\NoDiscard('handle() returns the number of backups removed.')]
    public function handle(array $uuids): int
    {
        $backups = BackupEloquentModel::query()->whereIn('uuid', $uuids)->get();

        foreach ($backups as $backup) {
            $disk = $this->filesystem->disk($backup->disk);

            if ($backup->path !== null && $disk->exists($backup->path)) {
                $disk->delete($backup->path);
            }
        }

        $deleted = BackupEloquentModel::query()->whereIn('uuid', $uuids)->delete();

        $this->audit->log('backup.bulk_deleted', null, ['count' => $deleted], null, 'backups.backup');

        return $deleted;
    }
}
