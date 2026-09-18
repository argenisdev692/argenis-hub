<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * CV picker row (spec US-1 CA-1): everything the operator needs to choose,
 * nothing sensitive (`raw_text` never leaves the Cvs module here).
 */
final readonly class CvOption
{
    public function __construct(
        public string $uuid,
        public string $title,
        public string $niche,
        public string $fileType,
        public bool $isPrimary,
        public string $updatedAt,
        public bool $importable,
    ) {}
}
