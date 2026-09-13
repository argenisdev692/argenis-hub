<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * Findings plus the external calls it took to get them, so research
 * consumption is counted separately from AI calls (FR-13i).
 */
final readonly class ResearchBatch
{
    /**
     * @param  list<ResearchFinding>  $findings
     */
    public function __construct(
        public array $findings = [],
        public int $calls = 0,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->findings === [];
    }

    public function merge(self $other): self
    {
        return new self([...$this->findings, ...$other->findings], $this->calls + $other->calls);
    }
}
