<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\DTOs\VideoEditListItemData;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

/**
 * The caller's edit history, newest first (E1 · US-6).
 */
final readonly class ListVideoEditsHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
    ) {}

    /**
     * @return LengthAwarePaginator<int, VideoEditListItemData>
     */
    #[\NoDiscard]
    public function handle(VideoEditFilterData $filters, int $userId): LengthAwarePaginator
    {
        return $this->edits->paginateOwned($userId, $filters);
    }
}
