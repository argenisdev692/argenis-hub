<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * One pooled provider hit, already mapped to a stable shape.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public string $snippet,
        public float $score,
    ) {}
}
