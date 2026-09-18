<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

/**
 * One search request (spec US-7, FR-12). Templates only — service ×
 * technology × country or a known company name. Person names, professional
 * networks and contact-data brokers never reach the provider (FR-13/FR-25,
 * enforced in the adapter guard).
 */
final readonly class SearchQuery
{
    public function __construct(
        public string $text,
        public string $purpose,
        public ?string $wave = null,
        public ?string $family = null,
        public ?string $country = null,
        public string $depth = 'advanced',
        public int $maxResults = 10,
    ) {}
}
