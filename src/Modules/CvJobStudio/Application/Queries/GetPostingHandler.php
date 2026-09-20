<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioPostingDetailData;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

final readonly class GetPostingHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    #[\NoDiscard]
    public function handle(string $uuid, int $userId): StudioPostingDetailData
    {
        return $this->postings->detailForUser($uuid, $userId)
            ?? throw new PostingNotFoundException("Posting {$uuid} not found.");
    }
}
