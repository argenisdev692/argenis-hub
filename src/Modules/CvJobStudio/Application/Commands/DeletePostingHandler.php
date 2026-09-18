<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

final readonly class DeletePostingHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    public function handle(string $uuid, int $userId): void
    {
        if (! $this->postings->softDelete($uuid, $userId)) {
            throw new PostingNotFoundException("Posting {$uuid} not found.");
        }
    }
}
