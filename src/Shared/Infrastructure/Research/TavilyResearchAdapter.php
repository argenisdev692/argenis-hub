<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Research;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * @see TavilyClientInterface
 */
final readonly class TavilyResearchAdapter implements TavilyClientInterface
{
    private const int MAX_QUERIES = 4;

    private const int TIMEOUT_SECONDS = 15;

    public function __construct(private CircuitBreakerInterface $breaker) {}

    /** @var list<string> */
    private const array TIME_RANGES = ['day', 'week', 'month', 'year'];

    /** @var list<string> */
    private const array SEARCH_DEPTHS = ['basic', 'advanced'];

    /**
     * Tavily documents full country names (`portugal`, `spain`); callers
     * pass ISO codes. Mapped where documented, lowercased ISO otherwise.
     *
     * @var array<string, string>
     */
    private const array COUNTRY_NAMES = ['PT' => 'portugal', 'ES' => 'spain'];

    private static function tavilyCountry(?string $country): ?string
    {
        if (! is_string($country) || trim($country) === '') {
            return null;
        }

        $iso = mb_strtoupper(trim($country));

        return self::COUNTRY_NAMES[$iso] ?? mb_strtolower($iso);
    }

    public function search(
        array $queries,
        ?string $timeRange = null,
        ?string $searchDepth = null,
        ?array $excludeDomains = null,
        ?string $country = null,
    ): array {
        $apiKey = (string) config('services.tavily.api_key');

        if ($apiKey === '') {
            return [];
        }

        $timeRange = in_array($timeRange, self::TIME_RANGES, true) ? $timeRange : null;
        $searchDepth = in_array($searchDepth, self::SEARCH_DEPTHS, true)
            ? $searchDepth
            : (string) config('services.tavily.search_depth', 'advanced');
        $excludeDomains = array_values(array_slice(array_filter(array_map(
            static fn ($domain): string => mb_strtolower(trim((string) $domain)),
            $excludeDomains ?? [],
        )), 0, 150));
        $country = self::tavilyCountry($country);
        $results = [];

        foreach (array_slice($queries, 0, self::MAX_QUERIES) as $query) {
            foreach ($this->searchOne($apiKey, $query, $timeRange, $searchDepth, $excludeDomains, $country) as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param  list<string>  $excludeDomains
     * @return list<array{title: string, url: string, content: string, score: float}>
     */
    private function searchOne(
        string $apiKey,
        string $query,
        ?string $timeRange,
        string $searchDepth,
        array $excludeDomains,
        ?string $country,
    ): array {
        return $this->breaker->call(
            'tavily',
            function () use ($apiKey, $query, $timeRange, $searchDepth, $excludeDomains, $country): array {
                $payload = [
                    'query' => $query,
                    'search_depth' => $searchDepth,
                    'max_results' => (int) config('services.tavily.max_results', 5),
                    'auto_parameters' => false,
                ];

                if ($timeRange !== null) {
                    $payload['time_range'] = $timeRange;
                }

                if ($excludeDomains !== []) {
                    $payload['exclude_domains'] = $excludeDomains;
                }

                if ($country !== null) {
                    $payload['country'] = $country;
                }

                $response = Http::withToken($apiKey)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->retry(1, 500)
                    ->post((string) config('services.tavily.url'), $payload);

                if ($response->failed()) {
                    throw new RuntimeException("Tavily search failed with status {$response->status()}.");
                }

                return array_map(
                    static fn (array $result): array => [
                        'title' => (string) ($result['title'] ?? ''),
                        'url' => (string) ($result['url'] ?? ''),
                        'content' => (string) ($result['content'] ?? ''),
                        'score' => (float) ($result['score'] ?? 0),
                    ],
                    (array) $response->json('results', []),
                );
            },
            function (Throwable $e) use ($query): array {
                Log::warning('tavily.search_failed', ['query' => $query, 'error' => $e->getMessage()]);

                return [];
            },
        );
    }
}
