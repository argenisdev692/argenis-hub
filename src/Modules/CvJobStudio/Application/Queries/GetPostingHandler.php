<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

final readonly class GetPostingHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    #[\NoDiscard]
    public function handle(string $uuid, int $userId): StudioPostingEloquentModel
    {
        return $this->postings->findByUuidForUser($uuid, $userId)
            ?? throw new PostingNotFoundException("Posting {$uuid} not found.");
    }
}
