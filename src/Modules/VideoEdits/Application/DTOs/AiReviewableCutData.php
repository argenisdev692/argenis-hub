<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One cut as the review modal — and now the PDF — shows it.
 *
 * `text` is what is removed, `keepText` is what the viewer hears instead, and
 * both come from the transcript (plus, for an AI proposal, the model's corrected
 * sentence). `explanation` is model-written and must be rendered as plain text
 * (OWASP LLM05).
 *
 * `origin` is what tells an automatic cut from a proposal: the screen groups by
 * it, and the PDF prints it, so the owner can see that a silence was not a
 * judgement call.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiReviewableCutData extends Data
{
    public function __construct(
        public string $id,
        public CutReason $reason,
        public DecisionOrigin $origin,
        public string $producer,
        public int $startMs,
        public int $endMs,
        public int $durationMs,
        public float $confidence,
        public string $text,
        public ?string $keepText,
        public string $contextBefore,
        public string $contextAfter,
        public ?string $explanation,
        public bool $preselected,
        public bool $approved,
    ) {}

    public static function fromCut(AiReviewableCut $cut, bool $approved = false): self
    {
        return new self(
            id: $cut->id,
            reason: $cut->reason,
            origin: $cut->origin,
            producer: $cut->producer,
            startMs: $cut->startMs,
            endMs: $cut->endMs,
            durationMs: $cut->endMs - $cut->startMs,
            confidence: $cut->confidence,
            text: $cut->text,
            keepText: $cut->keepText,
            contextBefore: $cut->contextBefore,
            contextAfter: $cut->contextAfter,
            explanation: $cut->explanation,
            preselected: $cut->preselected,
            approved: $approved,
        );
    }
}
