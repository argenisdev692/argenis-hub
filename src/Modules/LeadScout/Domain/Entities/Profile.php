<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;

/**
 * One version of the operator's matching profile, derived from a CV
 * (spec US-1). Versions are immutable: tuning mints a new one.
 */
final readonly class Profile
{
    /**
     * @param  list<string>  $confirmedSkills
     * @param  list<string>  $potentialSkills
     * @param  list<array<string, mixed>>  $proofPoints
     * @param  array<string, string>  $languages
     * @param  list<string>|null  $targetCountries
     * @param  array<string, mixed>|null  $weights
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $userId,
        public int $version,
        public ?string $sourceCvUuid,
        public ?string $cvHash,
        public array $confirmedSkills,
        public array $potentialSkills,
        public array $proofPoints,
        public array $languages,
        public ?int $minRateCents,
        public ?array $targetCountries,
        public ?array $weights,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}
