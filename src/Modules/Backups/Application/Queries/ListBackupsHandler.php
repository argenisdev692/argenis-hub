<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Backups\Application\DTOs\BackupData;
use Modules\Backups\Application\DTOs\BackupFilterData;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

/**
 * Paginated, filtered admin list of backup archives. No relations are touched —
 * the row is self-contained, so there is nothing here to N+1 on.
 */
final readonly class ListBackupsHandler
{
    #[\NoDiscard('handle() returns the paginated backup list.')]
    public function handle(BackupFilterData $filters): LengthAwarePaginator
    {
        return BackupEloquentModel::query()
            ->applyFilters($filters)
            ->select(['id', 'uuid', 'disk', 'path', 'filename', 'size_bytes', 'status', 'connection', 'error', 'started_at', 'finished_at', 'created_at', 'updated_at'])
            ->paginate($filters->perPage, page: $filters->page)
            ->through(BackupData::fromModel(...));
    }
}
