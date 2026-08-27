<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Commands;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Removes one backup: the archive file on its disk, then the index row. This is
 * a HARD delete — a backup archive is immutable and, once gone from storage,
 * cannot be soft-restored — so the business action is recorded through the
 * {@see AuditPort} rather than relying on a `deleted_at` trail.
 */
final readonly class DeleteBackupHandler
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private AuditPort $audit,
    ) {}

    /**
     * @throws ModelNotFoundException<BackupEloquentModel>
     */
    public function handle(string $uuid): void
    {
        $backup = BackupEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $disk = $this->filesystem->disk($backup->disk);

        if ($backup->path !== null && $disk->exists($backup->path)) {
            $disk->delete($backup->path);
        }

        $backup->delete();

        $this->audit->log(
            'backup.deleted',
            null,
            ['filename' => $backup->filename, 'disk' => $backup->disk, 'path' => $backup->path],
            null,
            'backups.backup',
        );
    }
}
