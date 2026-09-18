<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

final readonly class ListPostingsHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    #[\NoDiscard]
    public function handle(StudioPostingFilterData $filters, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->postings->paginate($filters, $perPage, $userId);
    }
}
