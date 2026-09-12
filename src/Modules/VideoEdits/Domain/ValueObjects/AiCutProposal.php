<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\CutReason;

/**
 * One cut the AI proposes, addressed by TRANSCRIPT WORD INDEX rather than by
 * time — the mechanism that makes V3 frame-accurate.
 *
 * Gemini reports video positions as `MM:SS` (second resolution, 1 FPS visual
 * sampling), which is far too coarse for a cut: a ±1 s error clips the next
 * sentence or leaves the flubbed word in. Whisper already knows that word 412
 * starts at 04:15.320, so the model is asked which WORDS to remove and the
 * exact milliseconds come from the transcript. The model never invents a
 * timestamp.
 */
final readonly class AiCutProposal
{
    public function __construct(
        public CutReason $reason,
        public int $startWordIndex,
        public int $endWordIndex,
        public float $confidence,
        public ?string $evidence = null,
    ) {}
}
