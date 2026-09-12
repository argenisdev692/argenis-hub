<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\SpeechCategory;

/**
 * One disfluency the detector found, before it becomes a {@see CutDecision}.
 *
 * The intermediate type exists so the detector stays a pure domain service: it
 * knows about speech, not about producers, origins or the persistence shape of
 * a decision. The producer does that mapping.
 */
final readonly class SpeechDetection
{
    /**
     * @param  array<string, scalar|null>  $evidence
     */
    public function __construct(
        public SpeechCategory $category,
        public int $startMs,
        public int $endMs,
        public ?float $confidence = null,
        public array $evidence = [],
    ) {}

    public function durationMs(): int
    {
        return $this->endMs - $this->startMs;
    }
}
