<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;

/**
 * Everything scoring, extraction and tailoring read about one posting, as
 * plain values: profile rules, requirements, gate verdicts and the newest
 * JD text. Built by the posting repository so no handler touches persistence.
 */
final readonly class PostingScoringContext
{
    /**
     * @param  array<string, mixed>|null  $rules  the owning profile's rules snapshot
     * @param  list<array{uuid: string, canonical_name: string, raw_text: string, tag: RequirementTag, nature: RequirementNature}>  $requirements
     * @param  list<array{gate_code: string, passed: bool}>  $gates
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public ?array $rules,
        public int $rulesVersion,
        public array $requirements,
        public array $gates,
        public string $latestText,
    ) {}

    /** The first gate this posting failed, or null when every gate passed (FR-12). */
    #[\NoDiscard]
    public function failedGate(): ?string
    {
        return array_first(array_filter($this->gates, static fn (array $gate): bool => ! $gate['passed']))['gate_code'] ?? null;
    }
}
