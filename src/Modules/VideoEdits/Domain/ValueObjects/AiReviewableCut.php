<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VideoEdits\Domain\Enums\CutReason;

/**
 * One AI-proposed cut as the owner sees it in the review (human-in-the-loop,
 * OWASP LLM06).
 *
 * Times are already resolved from the transcript's word timings, so an approved
 * cut becomes a decision without calling the model again. `$text` and the two
 * context strings come from the transcript, NOT from the model: what the owner
 * approves is exactly what Whisper heard in that span. Only `$explanation` is
 * model-written, and it is display-only.
 *
 * `$preselected` carries the old auto-apply threshold forward as a suggestion:
 * confident proposals start ticked, the rest start unticked, and the owner has
 * the last word on all of them.
 */
final readonly class AiReviewableCut
{
    public function __construct(
        public string $id,
        public CutReason $reason,
        public int $startMs,
        public int $endMs,
        public float $confidence,
        public string $text,
        public string $contextBefore = '',
        public string $contextAfter = '',
        public ?string $explanation = null,
        public bool $preselected = false,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('A reviewable cut needs an id.');
        }

        if ($startMs < 0 || $endMs <= $startMs) {
            throw new InvalidArgumentException('A reviewable cut must span a positive time range.');
        }
    }

    /**
     * @return array{id: string, reason: string, start_ms: int, end_ms: int, confidence: float, text: string, context_before: string, context_after: string, explanation: string|null, preselected: bool}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
            'confidence' => $this->confidence,
            'text' => $this->text,
            'context_before' => $this->contextBefore,
            'context_after' => $this->contextAfter,
            'explanation' => $this->explanation,
            'preselected' => $this->preselected,
        ];
    }

    /**
     * Stored JSON is data from a column, not a trusted object: a row that no
     * longer describes a valid cut is dropped rather than resurrected.
     *
     * @param  array<string, mixed>  $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): ?self
    {
        $reason = CutReason::tryFrom((string) ($row['reason'] ?? ''));

        if ($reason === null || ! $reason->isAiProposable()) {
            return null;
        }

        try {
            return new self(
                id: (string) ($row['id'] ?? ''),
                reason: $reason,
                startMs: (int) ($row['start_ms'] ?? -1),
                endMs: (int) ($row['end_ms'] ?? -1),
                confidence: (float) ($row['confidence'] ?? 0),
                text: (string) ($row['text'] ?? ''),
                contextBefore: (string) ($row['context_before'] ?? ''),
                contextAfter: (string) ($row['context_after'] ?? ''),
                explanation: isset($row['explanation']) ? (string) $row['explanation'] : null,
                preselected: (bool) ($row['preselected'] ?? false),
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
