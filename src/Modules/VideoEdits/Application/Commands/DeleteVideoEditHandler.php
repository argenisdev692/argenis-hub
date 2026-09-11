<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\StoragePort;

/**
 * Permanently deletes an edit: result video, any remaining sources and every
 * row (E7 · US-9 · FR-13). Refused while processing (D14). Only an audit entry
 * survives, carrying no file names, paths or URLs (plan D-1, D-2).
 */
final readonly class DeleteVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
        private StoragePort $storage,
        private AuditPort $audit,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     */
    public function handle(string $uuid, Authenticatable $user): void
    {
        $deleted = $this->edits->deleteOwned(
            $uuid,
            (int) $user->getAuthIdentifier(),
            function (VideoEditEloquentModel $edit): void {
                $paths = array_filter([
                    $edit->result_path,
                    ...$edit->sources->pluck('storage_path')->all(),
                ]);

                foreach ($paths as $path) {
                    $this->storage->delete((string) $path);
                }
            },
        );

        $this->audit->log(
            'video_edit.deleted',
            null,
            [
                'edit_uuid' => $deleted->uuid,
                'mode' => $deleted->mode->value,
                'status_at_deletion' => $deleted->status->value,
                'source_count' => $deleted->sources->count(),
            ],
            $user,
            'video-edits.video-edit',
        );
    }
}
