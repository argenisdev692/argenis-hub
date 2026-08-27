<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Modules\Backups\Domain\Enums\BackupStatus;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * Reconciles the `backups` index with the archive files actually present on the
 * backup disk: every `.zip` under `config('backup.backup.name')` is upserted as
 * a `Completed` row, and `Completed` rows whose file has since been removed (by
 * `backup:clean` or a manual delete) are pruned. `Failed` rows are never touched
 * — they have no file to reconcile against.
 *
 * Runs after every on-demand backup and nightly via the `backups:sync` command.
 */
final readonly class SyncBackupsHandler
{
    public function __construct(
        private FilesystemFactory $filesystem,
        private Config $config,
    ) {}

    #[\NoDiscard('handle() returns the number of archives currently on disk.')]
    public function handle(): int
    {
        /** @var list<string> $disks */
        $disks = (array) $this->config->get('backup.backup.destination.disks', ['local']);
        $diskName = (string) ($disks[0] ?? 'local');
        $prefix = (string) $this->config->get('backup.backup.name', 'laravel-backup');
        $connection = (string) $this->config->get('database.default');

        $disk = $this->filesystem->disk($diskName);

        $archives = array_values(array_filter(
            $disk->files($prefix),
            static fn (string $path): bool => str_ends_with(strtolower($path), '.zip'),
        ));

        foreach ($archives as $path) {
            $modifiedAt = CarbonImmutable::createFromTimestamp($disk->lastModified($path));

            BackupEloquentModel::query()->updateOrCreate(
                ['disk' => $diskName, 'path' => $path],
                [
                    'filename' => basename($path),
                    'size_bytes' => $disk->size($path),
                    'status' => BackupStatus::Completed,
                    'connection' => $connection,
                    'error' => null,
                    'started_at' => $modifiedAt,
                    'finished_at' => $modifiedAt,
                ],
            );
        }

        BackupEloquentModel::query()
            ->where('disk', $diskName)
            ->where('status', BackupStatus::Completed)
            ->when($archives !== [], fn ($query) => $query->whereNotIn('path', $archives))
            ->delete();

        return count($archives);
    }
}
