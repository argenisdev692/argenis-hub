<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\VideoEdits\Application\DTOs\VideoEditDetailData;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;

/**
 * Status, progress, summary and re-edit prefill of one of the caller's edits (E4 · US-4/5/7).
 */
final readonly class GetVideoEditHandler
{
    public function __construct(
        private VideoEditRepositoryPort $edits,
    ) {}

    /**
     * @throws VideoEditNotFoundException
     */
    #[\NoDiscard]
    public function handle(string $uuid, int $userId): VideoEditDetailData
    {
        $edit = $this->edits->findOwnedWithDetails($uuid, $userId)
            ?? throw VideoEditNotFoundException::forUuid($uuid);

        return VideoEditDetailData::fromModel($edit, CarbonImmutable::now());
    }
}
