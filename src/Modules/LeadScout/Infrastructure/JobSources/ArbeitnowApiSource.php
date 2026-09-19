<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\JobSources;

use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Domain\Ports\JobSourcePort;
use Modules\LeadScout\Domain\ValueObjects\RawPosting;

/**
 * Arbeitnow public job-board API (spec US-2, research R5). Follows `links`
 * pagination up to a configured page cap; remote/contract hints come from
 * the API's own `remote` / `job_types` fields.
 */
final readonly class ArbeitnowApiSource implements JobSourcePort
{
    public function supports(Source $source): bool
    {
        return $source->type === SourceType::JobApi && $source->name === 'Arbeitnow';
    }

    public function fetchSince(Source $source, ?string $cursor): iterable
    {
        $base = (string) config('lead-scout.job_sources.arbeitnow_url', 'https://www.arbeitnow.com/api/job-board-api');
        $maxPages = (int) config('lead-scout.job_sources.arbeitnow_max_pages', 4);
        $page = 1;
        $url = $base;

        while ($url !== '' && $page <= $maxPages) {
            $response = Http::timeout(15)->retry(1, 500)->get($url);

            if ($response->failed()) {
                throw new \RuntimeException("Arbeitnow fetch failed with status {$response->status()} on page {$page}.");
            }

            $payload = $response->json();

            foreach ((array) ($payload['data'] ?? []) as $job) {
                yield self::fromApiJob((array) $job);
            }

            $url = (string) ($payload['links']['next'] ?? '');
            $page++;
        }
    }

    /**
     * @param  array<string, mixed>  $job
     */
    public static function fromApiJob(array $job): RawPosting
    {
        $types = implode(' ', array_map(strtolower(...), (array) ($job['job_types'] ?? [])));

        return new RawPosting(
            title: (string) ($job['title'] ?? 'Untitled'),
            companyName: (string) ($job['company_name'] ?? 'Unknown'),
            location: isset($job['location']) ? (string) $job['location'] : null,
            country: null,
            remoteMode: ((bool) ($job['remote'] ?? false) || str_contains($types, 'remote')) ? 'remote' : null,
            contractType: str_contains($types, 'freelance') || str_contains($types, 'contract') ? 'freelance' : null,
            language: null,
            publishedAt: isset($job['created_at']) ? (string) $job['created_at'] : null,
            sourceUrl: (string) ($job['url'] ?? ''),
            bodyText: isset($job['description']) ? (string) $job['description'] : null,
        );
    }
}
