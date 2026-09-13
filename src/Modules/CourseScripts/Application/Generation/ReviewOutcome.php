<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Generation;

use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Domain\ValueObjects\ReviewVerdict;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;

final readonly class ReviewOutcome
{
    public function __construct(
        public ScriptDraft $draft,
        public ReviewVerdict $scriptVerdict,
        public ?ReviewVerdict $practiceVerdict,
        public bool $passed,
        public int $iterations,
        public CallUsage $usage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'review_scores' => $this->scriptVerdict->scores,
            'review_objections' => $this->scriptVerdict->objections,
            'review_iterations' => $this->iterations,
            'passed_review' => $this->passed,
            'practice_review_scores' => $this->practiceVerdict?->scores,
            'practice_review_objections' => $this->practiceVerdict?->objections,
        ];
    }
}
