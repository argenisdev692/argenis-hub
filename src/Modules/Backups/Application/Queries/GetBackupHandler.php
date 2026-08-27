<?php

declare(strict_types=1);

namespace Modules\Backups\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Backups\Application\DTOs\BackupData;
use Modules\Backups\Infrastructure\Persistence\Eloquent\Models\BackupEloquentModel;

final readonly class GetBackupHandler
{
    /**
     * @throws ModelNotFoundException<BackupEloquentModel>
     */
    #[\NoDiscard('handle() returns the requested backup.')]
    public function handle(string $uuid): BackupData
    {
        $backup = BackupEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        return BackupData::fromModel($backup);
    }
}
