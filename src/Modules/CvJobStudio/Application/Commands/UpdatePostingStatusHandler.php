<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/** Candidate-side status (T-080, FR-25): saved / applied / dismissed / skipped. */
final readonly class UpdatePostingStatusHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(string $uuid, string $status, int $userId): void
    {
        if (! $this->postings->setStatus($uuid, $userId, $status)) {
            throw new PostingNotFoundException("Posting {$uuid} not found.");
        }
    }
}
