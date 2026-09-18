<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Exceptions\RejectedSearchQueryException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Domain\ValueObjects\CanonicalDomain;
use Modules\LeadScout\Domain\ValueObjects\SearchQuery;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSearchQueryEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\EnrichCompanyJob;

/**
 * Agency discovery without vacancies (spec US-7, US-9, T044): systematic
 * queries (service × technology × place × commercial model) spread across
 * the three waves IN PARALLEL by weight (~60/30/10%), never repeating a
 * combination inside the cache window.
 *
 * Employment portals, directories, freelance marketplaces and social or
 * professional networks are discarded; only new canonical domains survive —
 * existing or suppressed ones stop here with zero page requests and zero
 * AI calls. Each survivor keeps its origin query and wave, then chains
 * into enrichment.
 *
 * @return array{queries: int, new_companies: int, skipped: int}
 */
final readonly class DiscoverAgenciesHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private SearchPort $search,
        private SuppressionGate $gate,
    ) {}

    public function handle(?string $wave = null, ?string $country = null, ?string $family = null): array
    {
        $planned = $this->planQueries($wave, $country, $family);
        $report = ['queries' => 0, 'new_companies' => 0, 'skipped' => 0];

        foreach ($planned as $plan) {
            $report['queries']++;

            try {
                $results = $this->search->search(new SearchQuery(
                    text: $plan['text'],
                    purpose: 'discovery',
                    wave: $plan['wave'],
                    family: $plan['family'],
                    country: $plan['country'],
                    depth: 'advanced',
                    maxResults: 10,
                ));
            } catch (RejectedSearchQueryException) {
                $report['skipped']++;

                continue;
            } catch (\Exception) {
                $report['skipped']++;

                continue;
            }

            $created = 0;

            foreach ($results as $result) {
                if ($this->isDeniedHost($result->url)) {
                    $report['skipped']++;

                    continue;
                }

                try {
                    $domain = CanonicalDomain::fromUrl($result->url)->value;
                } catch (\InvalidArgumentException) {
                    $report['skipped']++;

                    continue;
                }

                $company = $this->register($domain, $plan, $result->title);

                if ($company === null) {
                    $report['skipped']++;

                    continue;
                }

                $created++;
                $report['new_companies']++;
                EnrichCompanyJob::dispatch($company->uuid);
            }

            $this->recordEffectiveness($plan, $created);
        }

        Log::info('lead-scout.discover_finished', [
            'wave' => $wave,
            'queries' => $report['queries'],
            'new_companies' => $report['new_companies'],
            'skipped' => $report['skipped'],
        ]);

        return $report;
    }

    /**
     * Weighted query plan: waves run in parallel by share, not sequentially.
     *
     * @return list<array{text: string, wave: string, family: string, country: ?string}>
     */
    #[\NoDiscard('Planned queries must be captured')]
    public function planQueries(?string $wave, ?string $country, ?string $family): array
    {
        $weights = (array) config('lead-scout.discovery.weights', ['wave1' => 60, 'wave2' => 30, 'wave3' => 10]);
        $perRun = (int) config('lead-scout.discovery.queries_per_run', 15);
        $countries = (array) config('lead-scout.discovery.countries', []);
        $families = (array) config('lead-scout.discovery.families', []);

        $waves = $wave !== null ? [$wave] : array_keys($weights);
        $planned = [];

        foreach ($waves as $waveKey) {
            $share = (int) ($weights[$waveKey] ?? 0);

            if ($share <= 0) {
                continue;
            }

            $waveCountries = $countries[$waveKey] ?? [];
            $waveFamilies = $families[$waveKey] ?? [];

            if ($country !== null) {
                $waveCountries = array_values(array_filter(
                    $waveCountries,
                    static fn (array $c): bool => mb_strtoupper((string) ($c['iso'] ?? '')) === mb_strtoupper($country),
                ));
            }

            if ($family !== null) {
                $waveFamilies = array_values(array_filter(
                    $waveFamilies,
                    static fn (string $f): bool => mb_stripos($f, $family) !== false,
                ));
            }

            if ($waveCountries === [] || $waveFamilies === []) {
                continue;
            }

            $count = $wave !== null && $country !== null && $family !== null
                ? 1
                : max(1, (int) round($perRun * $share / 100));

            $planned = [...$planned, ...$this->spreadQueries($waveKey, $waveCountries, $waveFamilies, $count)];
        }

        return $planned;
    }

    /**
     * @param  list<array{iso: string, timezone: string, language: string}>  $countries
     * @param  list<string>  $families
     * @return list<array{text: string, wave: string, family: string, country: ?string}>
     */
    private function spreadQueries(string $wave, array $countries, array $families, int $count): array
    {
        $planned = [];
        $familyIndex = 0;
        $countryIndex = 0;

        for ($i = 0; $i < $count; $i++) {
            $family = $families[$familyIndex % count($families)];
            $countryRow = $countries[$countryIndex % count($countries)];
            $familyIndex++;
            $countryIndex++;

            $text = str_replace('{place}', (string) $countryRow['iso'], $family);

            $planned[] = [
                'text' => $text,
                'wave' => $wave,
                'family' => $family,
                'country' => (string) $countryRow['iso'],
            ];
        }

        return $planned;
    }

    private function register(string $domain, array $plan, string $title): ?ScoutCompanyEloquentModel
    {
        if ($this->companies->findByDomain($domain) !== null) {
            return null;
        }

        $candidates = $this->companies->suppressionsMatching($domain, null, null)
            ->map(static fn ($row): array => [
                'canonical_domain' => $row->canonical_domain,
                'tax_id' => $row->tax_id,
                'name' => $row->name,
            ])
            ->all();

        if ($this->gate->isSuppressed($domain, null, null, $candidates)) {
            return null;
        }

        return $this->companies->create([
            'canonical_domain' => $domain,
            'name' => mb_substr(trim($title) !== '' ? trim($title) : $domain, 0, 255),
            'country' => $plan['country'],
            'origin' => CompanyOrigin::Discovery->value,
            'origin_ref' => mb_substr($plan['text'], 0, 255),
            'discovery_wave' => $plan['wave'],
            'timezone_overlap_hours' => $this->overlapHours($plan['country']),
        ]);
    }

    private function isDeniedHost(string $url): bool
    {
        if (preg_match('~^[a-z][a-z0-9+.-]*://([^/:?#]+)~i', trim($url), $m) !== 1) {
            return true;
        }

        $bare = (string) preg_replace('/^www\./', '', mb_strtolower($m[1]));

        foreach ((array) config('lead-scout.result_domain_denylist', []) as $denied) {
            $denied = mb_strtolower(trim((string) $denied));

            if ($denied !== '' && ($bare === $denied || str_ends_with($bare, '.'.$denied))) {
                return true;
            }
        }

        return false;
    }

    private function overlapHours(?string $iso): ?int
    {
        if ($iso === null) {
            return null;
        }

        $zone = null;

        foreach ((array) config('lead-scout.discovery.countries', []) as $waveCountries) {
            foreach ($waveCountries as $row) {
                if (mb_strtoupper((string) ($row['iso'] ?? '')) === mb_strtoupper($iso)) {
                    $zone = (string) $row['timezone'];

                    break 2;
                }
            }
        }

        if ($zone === null) {
            return null;
        }

        try {
            $at = CarbonImmutable::now();
            $diff = abs(
                (new \DateTimeZone($zone))->getOffset($at->toDateTime())
                - (new \DateTimeZone('Europe/Lisbon'))->getOffset($at->toDateTime()),
            ) / 3600;

            return (int) max(0, 9 - $diff);
        } catch (\Exception) {
            return null;
        }
    }

    private function recordEffectiveness(array $plan, int $created): void
    {
        $hash = hash('sha256', mb_strtolower(trim($plan['text'])).'|advanced|'.($plan['country'] ?? ''));

        ScoutSearchQueryEloquentModel::query()
            ->where('query_hash', $hash)
            ->orderByDesc('id')
            ->first()
            ?->increment('new_companies_count', $created);
    }
}
