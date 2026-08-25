<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Docs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Shared\Domain\Ports\DocsVerificationPort;
use Shared\Infrastructure\Research\TavilyResearchAdapter;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * Context7 REST adapter — resolves a human library name to a Context7 library
 * id, then pulls topic-scoped documentation snippets for it. Mirrors
 * {@see TavilyResearchAdapter}: pure transport + circuit breaker, NO caching
 * (result caching belongs to the consuming module adapter, which owns the
 * business-specific key/TTL).
 *
 * Graceful degradation is the contract, not a nicety: a missing API key, an
 * open breaker, a 4xx/5xx or an unknown library all resolve to an empty
 * result so a multi-hour content generation is never aborted by a docs
 * lookup (spec FR-15).
 *
 * Security: snippets are third-party text that ends up inside an LLM prompt
 * (OWASP LLM01). They are control-char stripped and length-capped here so a
 * poisoned docs page cannot smuggle an oversized instruction block into a
 * downstream prompt.
 */
final readonly class Context7DocsAdapter implements Context7ClientInterface, DocsVerificationPort
{
    private const int TIMEOUT_SECONDS = 12;

    private const int SNIPPET_MAX_CHARS = 800;

    private const string BREAKER_SERVICE = 'context7';

    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function lookup(string $libraryName, string $topicContext): array
    {
        $library = trim($libraryName);

        if ($library === '' || $this->apiKey() === '') {
            return [];
        }

        $resolved = $this->resolveLibrary($library, $topicContext);

        if ($resolved === null) {
            return [];
        }

        return $this->fetchContext($resolved['id'], $topicContext);
    }

    public function resolveLibrary(string $libraryName, string $topicContext): ?array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === '' || trim($libraryName) === '') {
            return null;
        }

        return $this->breaker->call(
            self::BREAKER_SERVICE,
            function () use ($apiKey, $libraryName, $topicContext): ?array {
                $response = Http::withToken($apiKey)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->retry(1, 500)
                    ->get($this->endpoint('/api/v2/libs/search'), [
                        'libraryName' => $libraryName,
                        'query' => $topicContext,
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException("Context7 library search failed with status {$response->status()}.");
                }

                $best = array_first((array) $response->json('results', []));

                if (! is_array($best)) {
                    return null;
                }

                $id = (string) ($best['id'] ?? '');

                return $id === '' ? null : [
                    'id' => $id,
                    'title' => (string) ($best['title'] ?? $id),
                ];
            },
            function (Throwable $e) use ($libraryName): null {
                Log::warning('context7.resolve_failed', ['library' => $libraryName, 'error' => $e->getMessage()]);

                return null;
            },
        );
    }

    public function fetchContext(string $libraryId, string $topicContext): array
    {
        $apiKey = $this->apiKey();

        if ($apiKey === '' || trim($libraryId) === '') {
            return [];
        }

        return $this->breaker->call(
            self::BREAKER_SERVICE,
            function () use ($apiKey, $libraryId, $topicContext): array {
                $response = Http::withToken($apiKey)
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->retry(1, 500)
                    ->get($this->endpoint('/api/v2/context'), [
                        'libraryId' => $libraryId,
                        'query' => $topicContext,
                        'type' => 'json',
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException("Context7 context fetch failed with status {$response->status()}.");
                }

                return $this->mapSnippets($libraryId, (array) $response->json());
            },
            function (Throwable $e) use ($libraryId): array {
                Log::warning('context7.context_failed', ['library_id' => $libraryId, 'error' => $e->getMessage()]);

                return [];
            },
        );
    }

    /**
     * Flattens Context7's two snippet families (`codeSnippets` with a nested
     * `codeList`, and prose `infoSnippets`) into the single shape the Domain
     * port promises, newest/most-relevant first as returned by the provider.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{library: string, title: string, url: string, snippet: string}>
     */
    private function mapSnippets(string $libraryId, array $payload): array
    {
        $maxSnippets = max(1, (int) config('services.context7.max_snippets', 5));
        $fallbackUrl = $this->libraryUrl($libraryId);
        $snippets = [];

        foreach ((array) ($payload['codeSnippets'] ?? []) as $snippet) {
            if (! is_array($snippet)) {
                continue;
            }

            $code = implode("\n\n", array_map(
                static fn (mixed $entry): string => is_array($entry) ? (string) ($entry['code'] ?? '') : '',
                (array) ($snippet['codeList'] ?? []),
            ));

            $body = trim(((string) ($snippet['codeDescription'] ?? ''))."\n".$code);

            if ($body === '') {
                continue;
            }

            $snippets[] = [
                'library' => $libraryId,
                'title' => (string) ($snippet['codeTitle'] ?? $libraryId),
                'url' => (string) ($snippet['source'] ?? $fallbackUrl),
                'snippet' => $this->sanitize($body),
            ];
        }

        foreach ((array) ($payload['infoSnippets'] ?? []) as $snippet) {
            if (! is_array($snippet)) {
                continue;
            }

            $content = trim((string) ($snippet['content'] ?? ''));

            if ($content === '') {
                continue;
            }

            $snippets[] = [
                'library' => $libraryId,
                'title' => (string) ($snippet['infoTitle'] ?? $snippet['title'] ?? $libraryId),
                'url' => (string) ($snippet['source'] ?? $fallbackUrl),
                'snippet' => $this->sanitize($content),
            ];
        }

        return array_slice($snippets, 0, $maxSnippets);
    }

    /**
     * Strips control characters (prompt-injection / log-forging vector) and
     * caps the length before the text reaches an LLM prompt.
     */
    private function sanitize(string $text): string
    {
        return $text
            |> (fn (string $value): string => (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value))
            |> trim(...)
            |> (fn (string $value): string => Str::limit($value, self::SNIPPET_MAX_CHARS));
    }

    private function libraryUrl(string $libraryId): string
    {
        return $this->baseUrl().'/'.ltrim($libraryId, '/');
    }

    private function endpoint(string $path): string
    {
        return $this->baseUrl().$path;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.context7.url', 'https://context7.com'), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.context7.api_key');
    }
}
