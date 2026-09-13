<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Commands;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\VideoEdits\Application\DTOs\BulkDeletedVideoEditsData;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;

/**
 * Permanently deletes a selection of the caller's edits (E7 · US-9).
 *
 * Delegates each row to {@see DeleteVideoEditHandler} so ownership, the
 * processing guard (D14), file removal and the per-edit audit entry stay in one
 * place. There is no bulk restore counterpart: deletion is permanent by
 * decision (Q2/Q5). A row that is processing or not owned is skipped, not
 * fatal — one busy render must not block clearing the rest of the history.
 */
final readonly class BulkDeleteVideoEditsHandler
{
    public function __construct(private DeleteVideoEditHandler $deleteVideoEdit) {}

    /**
     * @param  list<string>  $uuids
     */
    #[\NoDiscard]
    public function handle(array $uuids, Authenticatable $user): BulkDeletedVideoEditsData
    {
        $deleted = 0;

        foreach ($uuids as $uuid) {
            try {
                $this->deleteVideoEdit->handle($uuid, $user);
                $deleted++;
            } catch (VideoEditNotFoundException|VideoEditStateConflictException) {
                continue;
            }
        }

        return new BulkDeletedVideoEditsData(deleted: $deleted, skipped: count($uuids) - $deleted);
    }
}
