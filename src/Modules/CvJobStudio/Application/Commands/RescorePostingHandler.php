<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\ScorePostingInputData;
use Modules\CvJobStudio\Domain\Enums\ScoreBand;
use Modules\CvJobStudio\Domain\Exceptions\PostingNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioScoreRepositoryPort;
use Modules\CvJobStudio\Domain\Services\BandClassifier;
use Modules\CvJobStudio\Domain\Services\CapLadder;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioScoreEloquentModel;

/**
 * Recompute from stored rows with zero provider calls (T-066, FR-37, SC-2):
 * rules changes and A/B comparison without re-fetching. New score rows keep
 * their rules version, so v1 vs v2 stays measurable, never rewritten.
 *
 * `recomputeFromStored()` goes further: it rebuilds the total from the
 * stored components bag alone — the SC-2 proof that history remains
 * interpretable after a formula change (NFR-9).
 */
final readonly class RescorePostingHandler
{
    public function __construct(
        private ScorePostingHandler $score,
        private StudioScoreRepositoryPort $scores,
        private CapLadder $caps,
        private BandClassifier $bands,
    ) {}

    #[\NoDiscard]
    public function handle(string $postingUuid, ScorePostingInputData $input, int $userId): StudioScoreEloquentModel
    {
        return $this->score->handle($postingUuid, $input, $userId);
    }

    /**
     * @return array{total: float, band: ScoreBand, matches_stored: bool}
     */
    #[\NoDiscard]
    public function recomputeFromStored(string $postingUuid, int $userId, array $rules): array
    {
        $score = $this->scores->latestForPosting($postingUuid, $userId);

        if ($score === null) {
            throw new PostingNotFoundException("Score for posting {$postingUuid} not found.");
        }

        $components = $score->h_components ?? [];
        $denominator = (float) ($components['denominator'] ?? 0.0);
        $numerator = 0.0;

        foreach ($components['matches'] ?? [] as $match) {
            $numerator += (float) $match['credit_final'];
        }

        $h = $denominator > 0.0 ? 100.0 * $numerator / $denominator : 50.0;
        $blend = $rules['blend'];
        $raw = (float) $blend['h'] * $h + (float) $blend['s'] * (float) $score->s + (float) $blend['d'] * (float) $score->d;

        $capped = $this->caps->apply($raw, $components['cap_context'] ?? [], $rules['caps']);

        return [
            'total' => $capped['total'],
            'band' => $this->bands->classify($capped['total'], $rules['bands']),
            'matches_stored' => (float) $score->total_score === $capped['total'],
        ];
    }
}
