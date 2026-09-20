<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use DateTimeImmutable;
use Modules\CvJobStudio\Application\DTOs\StudioScoreData;
use Modules\CvJobStudio\Domain\ValueObjects\ScoreBreakdown;

interface StudioScoreRepositoryPort
{
    public function store(string $postingUuid, int $userId, ScoreBreakdown $breakdown, string $postingTextHash): StudioScoreData;

    /**
     * The stored component bag of the newest score (SC-2 recompute proof).
     *
     * @return array{h_components: array<string, mixed>, s: float, d: float, total_score: float}|null
     */
    public function latestComponents(string $postingUuid, int $userId): ?array;

    /**
     * The stored breakdown a narrative may explain — numbers only, never recomputed.
     *
     * @return array{total: float, band: string|null, matches: list<array{requirement: string, credit: float}>}|null
     */
    public function narrationBreakdown(string $scoreUuid, int $userId): ?array;

    /**
     * Scores whose apply priority is missing or older than `$staleBefore`,
     * row-locked — call inside a transaction.
     *
     * @return list<array{score_id: int, total_score: float, opportunity_rules: array<string, mixed>|null, channel: string|null, posted_at: DateTimeImmutable|null, relist_count: int, text: string, title: string}>
     */
    public function priorityCandidates(int $userId, DateTimeImmutable $staleBefore): array;

    /** @param  array<int, array{components: array<string, mixed>, factor: float, priority: float}>  $priorities  keyed by score id */
    public function recordPriorities(array $priorities): void;
}
