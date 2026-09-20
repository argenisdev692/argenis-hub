<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/** Restores the original source as preferred (T-119). */
final readonly class UnlinkResolutionHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(string $postingUuid, int $userId): void
    {
        if (! $this->postings->unlinkResolution($postingUuid, $userId)) {
            throw new PostingNotFoundException("Posting {$postingUuid} not found.");
        }
    }
}
