<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;

/**
 * One cut as the owner sees it in the review (human-in-the-loop, OWASP LLM06).
 *
 * **Every cut is reviewable, not only the AI's.** Silence, fillers, stutters and
 * the spoken markers are deterministic, but "deterministic" is not the same as
 * "intended": a dictionary entry can be wrong and a silence threshold can be too
 * aggressive. Putting them in the same table as the AI proposals means the owner
 * sees everything that will disappear before anything is rendered — which is the
 * only moment the decision is cheap.
 *
 * Times are already resolved from the transcript's word timings, so an approved
 * cut becomes a decision without calling the model again. `$text`, `$keepText`
 * and the two context strings come from the transcript, NOT from the model: what
 * the owner approves is exactly what the transcription heard in that span — plus,
 * for an AI proposal, the corrected sentence the model says will remain. Only
 * `$explanation` is model-written, and it is display-only.
 *
 * `$preselected` carries the old auto-apply threshold forward as a suggestion:
 * confident proposals and every deterministic cut start ticked, the rest start
 * unticked, and the owner has the last word on all of them.
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
        public DecisionOrigin $origin = DecisionOrigin::Ai,
        public string $producer = 'ai_analyzer',
        /**
         * What the viewer will hear in place of `$text`: the corrected sentence
         * for a model proposal, or the surviving words around a dictionary cut.
         * Null when there is nothing honest to show (a removed silence has no
         * replacement text).
         */
        public ?string $keepText = null,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('A reviewable cut needs an id.');
        }

        if ($startMs < 0 || $endMs <= $startMs) {
            throw new InvalidArgumentException('A reviewable cut must span a positive time range.');
        }
    }

    /**
     * `system_detection` and `transcription` cuts are the automatic half: they
     * need no model and are shown as one group.
     */
    #[\NoDiscard]
    public function isAutomatic(): bool
    {
        return $this->origin !== DecisionOrigin::Ai;
    }

    /**
     * @return array{id: string, reason: string, origin: string, producer: string, start_ms: int, end_ms: int, confidence: float, text: string, keep_text: string|null, context_before: string, context_after: string, explanation: string|null, preselected: bool}
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason->value,
            'origin' => $this->origin->value,
            'producer' => $this->producer,
            'start_ms' => $this->startMs,
            'end_ms' => $this->endMs,
            'confidence' => $this->confidence,
            'text' => $this->text,
            'keep_text' => $this->keepText,
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
     * A review written before automatic cuts joined it has no `origin`, no
     * `producer` and no `keep_text`; those default to the AI-only world they
     * were written in, so an edit already waiting for approval keeps rendering
     * exactly what its owner is looking at.
     *
     * @param  array<string, mixed>  $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): ?self
    {
        $reason = CutReason::tryFrom((string) ($row['reason'] ?? ''));

        if ($reason === null) {
            return null;
        }

        $keepText = $row['keep_text'] ?? null;

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
                origin: DecisionOrigin::tryFrom((string) ($row['origin'] ?? '')) ?? DecisionOrigin::Ai,
                producer: (string) ($row['producer'] ?? 'ai_analyzer'),
                keepText: is_string($keepText) && $keepText !== '' ? $keepText : null,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
