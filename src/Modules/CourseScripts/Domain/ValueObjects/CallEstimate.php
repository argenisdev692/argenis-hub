<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * Pre-run estimate the author confirms (US-12, D21).
 */
final readonly class CallEstimate
{
    public function __construct(
        public int $aiWriteCalls,
        public int $aiReviewCalls,
        public int $researchCalls,
        public int $aiCeiling,
        public int $researchCeiling,
    ) {}

    public function aiCalls(): int
    {
        return $this->aiWriteCalls + $this->aiReviewCalls;
    }

    public function fits(): bool
    {
        return $this->aiCalls() <= $this->aiCeiling && $this->researchCalls <= $this->researchCeiling;
    }

    /**
     * @return array{ai_write_calls: int, ai_review_calls: int, research_calls: int}
     */
    public function confirmation(): array
    {
        return [
            'ai_write_calls' => $this->aiWriteCalls,
            'ai_review_calls' => $this->aiReviewCalls,
            'research_calls' => $this->researchCalls,
        ];
    }
}
