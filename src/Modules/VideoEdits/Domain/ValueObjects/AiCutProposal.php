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
 *
 * `keepText` is the model's answer to "what will the viewer hear instead": the
 * corrected sentence, written out. It is never used to cut anything — the range
 * is still the authority — it exists so the review row says what is removed AND
 * what survives, which is how a person decides whether the edit reads naturally.
 */
final readonly class AiCutProposal
{
    public function __construct(
        public CutReason $reason,
        public int $startWordIndex,
        public int $endWordIndex,
        public float $confidence,
        public ?string $evidence = null,
        public ?string $keepText = null,
    ) {}
}
