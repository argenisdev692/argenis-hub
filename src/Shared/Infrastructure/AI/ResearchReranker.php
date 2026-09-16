<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Ai\Reranking;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Relevance re-ordering of Tavily research before prompt injection.
 *
 * Tavily's own score ranks by query match; the cross-encoder behind
 * `Reranking` judges each snippet against the writer's actual brief, so the
 * few rows that survive the `array_slice(..., 10)` in the adapters are the
 * most useful ones instead of merely the first ones. Fail-soft by contract:
 * any failure (provider down, misconfigured reranker, empty input) returns
 * the input untouched — callers never branch on this.
 */
final readonly class ResearchReranker
{
    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param  list<array{title: string, url: string, content: string, score: float}>  $rows
     * @return list<array{title: string, url: string, content: string, score: float}>
     */
    public function rerank(string $query, array $rows, ?int $limit = null): array
    {
        if ($rows === []) {
            return [];
        }

        $documents = array_values(array_map(
            static fn (array $row): string => trim((string) ($row['title'] ?? '')."\n".(string) ($row['content'] ?? '')),
            $rows,
        ));

        try {
            $response = Reranking::of($documents)
                ->limit($limit)
                ->rerank($query, (string) $this->config->get('ai.default_for_reranking', 'cohere'));

            $ordered = [];

            foreach ($response->results as $ranked) {
                if (isset($rows[$ranked->index])) {
                    $ordered[] = $rows[$ranked->index];
                }
            }

            return $ordered === [] ? $rows : array_values($ordered);
        } catch (Throwable $exception) {
            // Class name only: provider errors can echo the brief (LLM02).
            $this->logger->warning('ai.rerank_failed', ['error' => $exception::class]);

            return $rows;
        }
    }
}
