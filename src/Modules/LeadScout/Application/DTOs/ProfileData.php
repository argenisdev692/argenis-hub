<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Versioned matching profile response (spec US-1). Carries the derived
 * profile only — never the CV text (FR-35).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ProfileData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly int $version,
        public readonly ?string $sourceCvUuid,
        /** @var list<string> */
        public readonly array $confirmedSkills,
        /** @var list<string> */
        public readonly array $potentialSkills,
        /** @var list<array<string, mixed>> */
        public readonly array $proofPoints,
        /** @var array<string, string> */
        public readonly array $languages,
        public readonly ?int $minRateCents,
        /** @var list<string>|null */
        public readonly ?array $targetCountries,
        /** @var array<string, mixed>|null */
        public readonly ?array $weights,
        public readonly bool $stale,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromEntity(Profile $profile, CvSourcePort $cvs, int $userId): self
    {
        return new self(
            uuid: $profile->uuid,
            version: $profile->version,
            sourceCvUuid: $profile->sourceCvUuid,
            confirmedSkills: $profile->confirmedSkills,
            potentialSkills: $profile->potentialSkills,
            proofPoints: $profile->proofPoints,
            languages: $profile->languages,
            minRateCents: $profile->minRateCents,
            targetCountries: $profile->targetCountries,
            weights: $profile->weights,
            stale: self::isStale($profile, $cvs, $userId),
            updatedAt: $profile->updatedAt?->format(DATE_ATOM),
        );
    }

    private static function isStale(Profile $profile, CvSourcePort $cvs, int $userId): bool
    {
        if ($profile->sourceCvUuid === null) {
            return false;
        }

        $current = $cvs->cvForUser($profile->sourceCvUuid, $userId);

        return $current === null || $current->contentHash !== $profile->cvHash;
    }
}
