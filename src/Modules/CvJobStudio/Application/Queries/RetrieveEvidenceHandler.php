<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Domain\Ports\EmbeddingPort;
use Modules\CvJobStudio\Domain\Ports\SimilaritySearchPort;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;
use Modules\CvJobStudio\Domain\Services\EvidenceRetriever;
use Throwable;

/**
 * RAG evidence pass (T-123, U-3): embeds the posting's requirements and
 * responsibilities in one batch, searches stored `cv_bullet` vectors, and
 * ranks with `EvidenceRetriever` (top-N bullets per target). Read-only —
 * never writes embeddings; missing vectors simply yield empty evidence.
 * An embedding outage returns empty evidence, never an error (NFR-17).
 */
final readonly class RetrieveEvidenceHandler
{
    public function __construct(
        private StudioPostingRepositoryPort $postings,
        private StudioCvRepositoryPort $cvs,
        private EmbeddingPort $embeddings,
        private SimilaritySearchPort $search,
        private EvidenceRetriever $retriever,
    ) {}

    /**
     * @return array{evidence: array<string, list<array{bullet_id: int, text: string, score: float}>>, proposals: list<array{from: string, to: string, kind: string}>}
     */
    #[\NoDiscard]
    public function handle(string $postingUuid, int $structureId, int $userId, int $topN = 3): array
    {
        $empty = ['evidence' => [], 'proposals' => []];

        $targets = $this->postings->requirementTargets($postingUuid, $userId);

        if ($targets === null) {
            return $empty;
        }

        $bullets = $this->cvs->bulletsForStructure($structureId, $userId);

        if ($targets === [] || $bullets === []) {
            return $empty;
        }

        try {
            $result = $this->embeddings->embed($targets);
        } catch (Throwable) {
            return $empty;
        }

        $matrix = [];

        foreach ($bullets as $bulletId => $text) {
            $matrix[$bulletId] = ['bullet_id' => $bulletId, 'text' => $text, 'similarities' => []];
        }

        foreach ($targets as $index => $target) {
            $vector = $result['vectors'][$index] ?? [];

            if ($vector === []) {
                continue;
            }

            try {
                $hits = $this->search->nearest('cv_bullet', $userId, $vector, $topN * 3);
            } catch (Throwable) {
                continue;
            }

            foreach ($hits as $hit) {
                $bulletId = (int) $hit['owner_id'];

                if (! isset($matrix[$bulletId])) {
                    continue;
                }

                $score = 1.0 - (float) $hit['distance'];

                $previous = $matrix[$bulletId]['similarities'][$target] ?? null;

                if ($previous === null || $score > $previous) {
                    $matrix[$bulletId]['similarities'][$target] = $score;
                }
            }
        }

        return $this->retriever->retrieve(array_values($matrix), $targets, $topN);
    }
}
