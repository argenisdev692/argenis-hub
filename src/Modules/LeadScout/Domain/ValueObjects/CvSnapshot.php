<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * Read-only view of an operator CV row (spec US-1, FR-1). The full text is
 * never copied into LeadScout storage and never reaches the IA, logs,
 * exports or Inertia props — only the derived profile does.
 */
final readonly class CvSnapshot
{
    public function __construct(
        public string $uuid,
        public string $fileType,
        public ?string $rawText,
        public string $contentHash,
        public string $updatedAt,
        public bool $isPrimary,
        public string $title,
    ) {}

    public function hasText(): bool
    {
        return $this->rawText !== null && trim($this->rawText) !== '';
    }
}
