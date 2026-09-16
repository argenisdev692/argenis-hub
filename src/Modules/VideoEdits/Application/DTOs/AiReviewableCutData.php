<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One AI-proposed cut as the review modal shows it. `text` and the context are
 * transcript words; `explanation` is model-written and must be rendered as
 * plain text (OWASP LLM05).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiReviewableCutData extends Data
{
    public function __construct(
        public string $id,
        public CutReason $reason,
        public int $startMs,
        public int $endMs,
        public int $durationMs,
        public float $confidence,
        public string $text,
        public string $contextBefore,
        public string $contextAfter,
        public ?string $explanation,
        public bool $preselected,
    ) {}

    public static function fromCut(AiReviewableCut $cut): self
    {
        return new self(
            id: $cut->id,
            reason: $cut->reason,
            startMs: $cut->startMs,
            endMs: $cut->endMs,
            durationMs: $cut->endMs - $cut->startMs,
            confidence: $cut->confidence,
            text: $cut->text,
            contextBefore: $cut->contextBefore,
            contextAfter: $cut->contextAfter,
            explanation: $cut->explanation,
            preselected: $cut->preselected,
        );
    }
}
