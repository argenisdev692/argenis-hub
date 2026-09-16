<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * The owner's say over every cut the AI proposed (human-in-the-loop,
 * OWASP LLM06).
 *
 * Pending until resolved; resolved exactly once. The resolution is stored with
 * the edit, so a retried render, or the second pass after approval, applies
 * the same approved cuts without asking the model again — and without charging
 * for a second analysis whose answer could differ.
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
     * @return array{cuts: list<array<string, mixed>>, reviewed_at: string|null, approved_cut_ids: list<string>, resolved_by_expiry: bool}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'cuts' => array_map(static fn (AiReviewableCut $cut): array => $cut->toArray(), $this->cuts),
            'reviewed_at' => $this->reviewedAt?->format(DateTimeInterface::ATOM),
            'approved_cut_ids' => $this->approvedCutIds,
            'resolved_by_expiry' => $this->resolvedByExpiry,
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
