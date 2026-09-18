<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Embeddings;

use Modules\CvJobStudio\Domain\Ports\SimilaritySearchPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;

/**
 * Exact brute-force search for the default (SQLite) suite. The pgsql group
 * covers the real HNSW path; this fake keeps every other test driver-free.
 */
final readonly class InMemorySimilaritySearch implements SimilaritySearchPort
{
    public function nearest(string $ownerType, int $userId, array $vector, int $limit): array
    {
        $rows = StudioEmbeddingEloquentModel::query()
            ->where('user_id', $userId)
            ->where('owner_type', $ownerType)
            ->get(['owner_id', 'embedding']);

        $scored = [];

        foreach ($rows as $row) {
            $candidate = is_array($row->embedding) ? array_map(floatval(...), $row->embedding) : [];

            if ($candidate === []) {
                continue;
            }

            $scored[] = ['owner_id' => (int) $row->owner_id, 'distance' => 1.0 - self::cosine($vector, $candidate)];
        }

        usort($scored, static fn (array $a, array $b): int => $a['distance'] <=> $b['distance']);

        return array_slice($scored, 0, $limit);
    }

    /** @param  list<float>  $a @param  list<float>  $b */
    private static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
