<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * One source found by research (FR-13a/b). Content is untrusted external text
 * (FR-13h) and is only ever handed to a model inside an untrusted block.
 */
final readonly class ResearchFinding
{
    public function __construct(
        public string $provider,
        public string $query,
        public string $url,
        public string $title,
        public string $content,
        public ?float $score = null,
        public bool $fullPageFetched = false,
        /** Persisted id once stored; null for a fresh result. */
        public ?int $id = null,
    ) {}

    public function withContent(string $content, bool $fullPageFetched): self
    {
        return clone ($this, ['content' => $content, 'fullPageFetched' => $fullPageFetched]);
    }
}
