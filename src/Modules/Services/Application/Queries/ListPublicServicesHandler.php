<?php

declare(strict_types=1);

namespace Modules\Services\Application\Queries;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Services\Application\DTOs\PublicServiceData;
use Modules\Services\Infrastructure\Persistence\Eloquent\Models\ServiceEloquentModel;

/**
 * Reads the service catalog for the public landing pages.
 *
 * Cached for the same reason {@see
 * \Modules\Company\Application\Queries\GetPublicCompanyHandler} is: this
 * endpoint is unauthenticated and answers every visitor of every external
 * site, for a table that changes only when an operator edits the catalog.
 * The cache key is injected by `ServicesServiceProvider` from {@see
 * \Modules\Services\Infrastructure\Cache\ServicePublicFeedCache}, which also
 * owns the flush — one key, one invalidation point.
 */
final readonly class ListPublicServicesHandler
{
    private const int TTL_MINUTES = 30;

    public function __construct(
        private Cache $cache,
        private string $cacheKey,
    ) {}

    /**
     * @return list<PublicServiceData>
     */
    #[\NoDiscard('handle() returns the public service catalog.')]
    public function handle(): array
    {
        return $this->cache->remember(
            $this->cacheKey,
            now()->addMinutes(self::TTL_MINUTES),
            static fn (): array => ServiceEloquentModel::query()
                ->active()
                ->orderBy('sort_order')
                ->get(['uuid', 'name', 'slug', 'description', 'sort_order'])
                ->map(PublicServiceData::fromModel(...))
                ->all(),
        );
    }
}
