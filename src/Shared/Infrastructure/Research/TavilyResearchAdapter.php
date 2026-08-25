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

    public function search(array $queries): array
    {
        $apiKey = (string) config('services.tavily.api_key');

        if ($apiKey === '') {
            return [];
        }

        $results = [];

        foreach (array_slice($queries, 0, self::MAX_QUERIES) as $query) {
            foreach ($this->searchOne($apiKey, $query) as $result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @return list<array{title: string, url: string, content: string, score: float}>
     */
    private function searchOne(string $apiKey, string $query): array
    {
        return $this->breaker->call(
            'tavily',
            function () use ($apiKey, $query): array {
                $response = Http::withToken($apiKey)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->retry(1, 500)
                    ->post((string) config('services.tavily.url'), [
                        'query' => $query,
                        'search_depth' => (string) config('services.tavily.search_depth', 'advanced'),
                        'max_results' => (int) config('services.tavily.max_results', 5),
                    ]);

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
