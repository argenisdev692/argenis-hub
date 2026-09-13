<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * Provider calls consumed by a step, split the way runs report them: AI
 * writing, AI review, research (FR-24, FR-13i).
 */
final readonly class CallUsage
{
    public function __construct(
        public int $aiWrite = 0,
        public int $aiReview = 0,
        public int $research = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public function add(self $other): self
    {
        return new self(
            $this->aiWrite + $other->aiWrite,
            $this->aiReview + $other->aiReview,
            $this->research + $other->research,
        );
    }

    public function ai(): int
    {
        return $this->aiWrite + $this->aiReview;
    }
}
