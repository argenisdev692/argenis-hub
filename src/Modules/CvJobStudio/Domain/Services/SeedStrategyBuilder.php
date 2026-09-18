<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Keywords × portals by access mode (T-029, CHG-5/CHG-18): `api_feed`
 * sources are harvested whole and filtered locally (keywords are filters,
 * not queries); `search_scoped`/`link_only` portals get `include_domains`
 * searches; `sitemap` sources are walked. Pure, unit-tested.
 */
final readonly class SeedStrategyBuilder
{
    /**
     * @param  array{keywords: list<string>, portals: list<array{name: string, access_mode: string, domains: list<string>}>}  $input
     * @return array<int, array{portal: string, mode: string, query: string|null, include_domains: list<string>}>
     */
    #[\NoDiscard]
    public function build(array $input): array
    {
        $seeds = [];

        foreach ($input['portals'] as $portal) {
            $seeds[] = match ($portal['access_mode']) {
                'api_feed' => ['portal' => $portal['name'], 'mode' => 'harvest_whole', 'query' => null, 'include_domains' => []],
                'sitemap' => ['portal' => $portal['name'], 'mode' => 'walk_sitemap', 'query' => null, 'include_domains' => []],
                default => [
                    'portal' => $portal['name'],
                    'mode' => 'search_scoped',
                    'query' => implode(' ', $input['keywords']),
                    'include_domains' => $portal['domains'],
                ],
            };
        }

        return $seeds;
    }
}
