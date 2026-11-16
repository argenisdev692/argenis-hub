<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * The owner's say over every cut proposed for this edit (human-in-the-loop,
 * OWASP LLM06).
 *
 * Two shapes live here, and the difference is what the render pass reads:
 *
 *  - **AI-only** (`$includesAutomatic = false`, the original behaviour): the
 *    review holds the model's proposals. Deterministic cuts — silence, fillers,
 *    stutters, the spoken marker — are recomputed on the render pass and applied
 *    without being asked about, because a literal match cannot be wrong.
 *  - **Unified** (`$includesAutomatic = true`): the review holds every cut that
 *    will be applied, automatic ones included, and the render pass uses exactly
 *    this list. Nothing is recomputed, so what renders is what was approved —
 *    including an automatic cut the owner unticked because the dictionary was
 *    wrong that once.
 *
 * Pending until resolved; resolved exactly once. The resolution is stored with
 * the edit, so a retried render, or the second pass after approval, applies the
 * same approved cuts without asking the model again — and without charging for a
 * second analysis whose answer could differ.
 *
 * An empty approval is a real answer ("keep everything"), which is why
 * resolution is `$reviewedAt`, not "has approvals".
 */
final readonly class AiCutReview
{
    /**
     * @param  list<AiReviewableCut>  $cuts
     * @param  list<string>  $approvedCutIds
     */
    public function __construct(
        public array $cuts,
        public ?DateTimeImmutable $reviewedAt = null,
        public array $approvedCutIds = [],
        public bool $resolvedByExpiry = false,
        public bool $includesAutomatic = false,
    ) {}

    /**
     * @param  list<AiReviewableCut>  $cuts
     */
    #[\NoDiscard]
    public static function pending(array $cuts): self
    {
        return new self($cuts);
    }

    /**
     * The unified review: every cut, automatic and AI, waiting for one answer.
     *
     * @param  list<AiReviewableCut>  $cuts
     */
    #[\NoDiscard]
    public static function pendingAll(array $cuts): self
    {
        return new self($cuts, includesAutomatic: true);
    }

    /**
     * The AI found nothing to cut: there is nothing for a person to approve,
     * so the review is born resolved and the edit renders straight away.
     */
    #[\NoDiscard]
    public static function nothingProposed(DateTimeImmutable $at): self
    {
        return new self([], $at);
    }

    public function isResolved(): bool
    {
        return $this->reviewedAt !== null;
    }

    /**
     * @param  list<string>  $approvedCutIds  must all be known — see {@see unknownCutIds()}
     */
    #[\NoDiscard]
    public function resolve(array $approvedCutIds, DateTimeImmutable $at, bool $byExpiry = false): self
    {
        return clone ($this, [
            'reviewedAt' => $at,
            'approvedCutIds' => array_values(array_unique($approvedCutIds)),
            'resolvedByExpiry' => $byExpiry,
        ]);
    }

    /**
     * @param  list<string>  $cutIds
     * @return list<string>
     */
    #[\NoDiscard]
    public function unknownCutIds(array $cutIds): array
    {
        $known = array_map(static fn (AiReviewableCut $cut): string => $cut->id, $this->cuts);

        return array_values(array_diff($cutIds, $known));
    }

    /**
     * @return list<AiReviewableCut>
     */
    #[\NoDiscard]
    public function approvedCuts(): array
    {
        if (! $this->isResolved()) {
            return [];
        }

        return array_values(array_filter(
            $this->cuts,
            fn (AiReviewableCut $cut): bool => in_array($cut->id, $this->approvedCutIds, true),
        ));
    }

    /**
     * The approved review as the decision set the planner renders from.
     *
     * Producer, reason and origin travel with the row, so a unified review can
     * carry a silence cut and a retake in the same list without either losing
     * which stage proposed it.
     *
     * @return list<CutDecision>
     */
    #[\NoDiscard]
    public function approvedDecisions(): array
    {
        return array_map(
            static fn (AiReviewableCut $cut): CutDecision => new CutDecision(
                producer: $cut->producer,
                reason: $cut->reason,
                origin: $cut->origin,
                startMs: $cut->startMs,
                endMs: $cut->endMs,
                confidence: $cut->confidence,
                evidence: [
                    'text' => mb_substr($cut->text, 0, 300),
                    'review_cut_id' => $cut->id,
                ],
            ),
            $this->approvedCuts(),
        );
    }

    /**
     * @return array{cuts: list<array<string, mixed>>, reviewed_at: string|null, approved_cut_ids: list<string>, resolved_by_expiry: bool, includes_automatic: bool}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'cuts' => array_map(static fn (AiReviewableCut $cut): array => $cut->toArray(), $this->cuts),
            'reviewed_at' => $this->reviewedAt?->format(DateTimeInterface::ATOM),
            'approved_cut_ids' => $this->approvedCutIds,
            'resolved_by_expiry' => $this->resolvedByExpiry,
            'includes_automatic' => $this->includesAutomatic,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[\NoDiscard]
    public static function fromArray(array $payload): self
    {
        $rows = is_array($payload['cuts'] ?? null) ? $payload['cuts'] : [];
        $approved = is_array($payload['approved_cut_ids'] ?? null) ? $payload['approved_cut_ids'] : [];

        return new self(
            cuts: array_values(array_filter(array_map(
                static fn (mixed $row): ?AiReviewableCut => is_array($row) ? AiReviewableCut::fromArray($row) : null,
                $rows,
            ))),
            reviewedAt: self::date($payload['reviewed_at'] ?? null),
            approvedCutIds: array_values(array_map(strval(...), array_filter($approved, is_string(...)))),
            resolvedByExpiry: (bool) ($payload['resolved_by_expiry'] ?? false),
            includesAutomatic: (bool) ($payload['includes_automatic'] ?? false),
        );
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
