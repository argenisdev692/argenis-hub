<?php

declare(strict_types=1);

namespace Modules\Portfolios\Application\Queries;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Portfolios\Application\DTOs\PublicPortfolioData;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * Reads the portfolio showcase for the public landing pages.
 *
 * Cached for the same reason the Services module's public feed handler is: this
 * endpoint is unauthenticated and answers every visitor of every external site,
 * for a table that changes only when an operator edits the showcase. The cache
 * key is injected by `PortfoliosServiceProvider` from `PortfolioPublicFeedCache`,
 * which also owns the flush — one key, one invalidation point.
 */
final readonly class ListPublicPortfoliosHandler
{
    private const int TTL_MINUTES = 30;

    public function __construct(
        private Cache $cache,
        private string $cacheKey,
    ) {}

    /**
     * @return list<PublicPortfolioData>
     */
    #[\NoDiscard('handle() returns the public portfolio showcase.')]
    public function handle(): array
    {
        return $this->cache->remember(
            $this->cacheKey,
            now()->addMinutes(self::TTL_MINUTES),
            static fn (): array => PortfolioEloquentModel::query()
                ->published()
                ->with('media:id,portfolio_id,path,sort_order')
                ->orderBy('sort_order')
                ->get([
                    'id', 'uuid', 'title', 'client_name', 'project_type', 'tech_stack',
                    'live_url', 'cover_path', 'video_path', 'description', 'published_at', 'sort_order',
                ])
                ->map(PublicPortfolioData::fromModel(...))
                ->all(),
        );
    }
}
