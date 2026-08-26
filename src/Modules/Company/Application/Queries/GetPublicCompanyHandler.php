<?php

declare(strict_types=1);

namespace Modules\Company\Application\Queries;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Company\Application\DTOs\PublicCompanyData;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;

/**
 * Reads the company for the public landing sites.
 *
 * Cached because this endpoint is unauthenticated and answers every visitor of
 * every external site: without it, a burst of landing-page traffic becomes a
 * burst of identical queries for a row that changes a few times a year. The
 * cache key is injected by `CompanyServiceProvider` from
 * `CompanyProfile::PUBLIC_CACHE_KEY`, which is also what the model's `saved` /
 * `deleted` hooks flush — one key, one invalidation point, and the Application
 * layer never has to name an Infrastructure constant.
 */
final readonly class GetPublicCompanyHandler
{
    private const int TTL_MINUTES = 30;

    public function __construct(
        private CompanyRepositoryPort $companies,
        private CompanyLogoStoragePort $logos,
        private Cache $cache,
        private string $cacheKey,
    ) {}

    #[\NoDiscard('handle() returns the public company payload.')]
    public function handle(): PublicCompanyData
    {
        return $this->cache->remember(
            $this->cacheKey,
            now()->addMinutes(self::TTL_MINUTES),
            function (): PublicCompanyData {
                $company = $this->companies->current();

                return PublicCompanyData::fromSnapshot($company, $this->logos->urls($company->logos));
            },
        );
    }
}
