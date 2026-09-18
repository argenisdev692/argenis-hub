<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Embeddings;

use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Domain\Ports\SimilaritySearchPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioEmbeddingEloquentModel;

/**
 * Filtered HNSW search (T-052). The transaction sets
 * `hnsw.iterative_scan = strict_order` + `ef_search = 100` per session:
 * without the iterative scan a selective `WHERE` silently under-returns
 * (T-000 proves it). `orderByVectorDistance` must emit `<=>` or the index is
 * skipped — asserted by T-000 with EXPLAIN.
 */
final readonly class PgVectorSimilaritySearch implements SimilaritySearchPort
{
    public function nearest(string $ownerType, int $userId, array $vector, int $limit): array
    {
        return DB::transaction(static function () use ($ownerType, $userId, $vector, $limit): array {
            DB::statement('SET LOCAL hnsw.iterative_scan = strict_order');
            DB::statement('SET LOCAL hnsw.ef_search = 100');

            return StudioEmbeddingEloquentModel::query()
                ->select(['id', 'owner_id'])
                ->selectVectorDistance('embedding', $vector, as: 'distance')
                ->where('user_id', $userId)
                ->where('owner_type', $ownerType)
                ->whereNull('deleted_at')
                ->orderByVectorDistance('embedding', $vector)
                ->limit($limit)
                ->get()
                ->map(static fn ($row): array => [
                    'owner_id' => (int) $row->owner_id,
                    'distance' => (float) $row->distance,
                ])
                ->all();
        });
    }
}
