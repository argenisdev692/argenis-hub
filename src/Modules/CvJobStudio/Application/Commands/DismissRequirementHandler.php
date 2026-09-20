<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/** Drops a mis-extracted requirement so the next rescore excludes it (T-067). */
final readonly class DismissRequirementHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(string $requirementUuid, int $userId): void
    {
        if (! $this->postings->dismissRequirement($requirementUuid, $userId)) {
            throw RecordNotFoundException::of('requirement', $requirementUuid);
        }
    }
}
