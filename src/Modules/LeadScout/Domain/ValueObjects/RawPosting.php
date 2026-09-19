<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * Normalized raw posting from any job source (spec FR-4). Plain readonly
 * object — validation happens at the source boundary, not here.
 */
final readonly class RawPosting
{
    public function __construct(
        public string $title,
        public string $companyName,
        public ?string $companyUrl = null,
        public ?string $location = null,
        public ?string $country = null,
        public ?string $remoteMode = null,
        public ?string $contractType = null,
        public ?string $language = null,
        public ?string $publishedAt = null,
        public string $sourceUrl = '',
        public ?string $bodyText = null,
    ) {}
}
