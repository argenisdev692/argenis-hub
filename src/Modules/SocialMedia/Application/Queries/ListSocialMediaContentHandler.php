<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentRepositoryPort;

final readonly class ListSocialMediaContentHandler
{
    public function __construct(private SocialMediaContentRepositoryPort $content) {}

    public function handle(SocialMediaContentFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->content->paginate($filters, $perPage);
    }
}
