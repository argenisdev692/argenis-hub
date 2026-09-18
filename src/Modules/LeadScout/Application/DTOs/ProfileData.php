<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Ports\CvSourcePort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;
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

    public static function fromModel(ScoutProfileEloquentModel $profile, CvSourcePort $cvs, int $userId): self
    {
        return new self(
            uuid: $profile->uuid,
            version: $profile->version,
            sourceCvUuid: $profile->source_cv_uuid,
            confirmedSkills: $profile->confirmed_skills ?? [],
            potentialSkills: $profile->potential_skills ?? [],
            proofPoints: $profile->proof_points ?? [],
            languages: $profile->languages ?? [],
            minRateCents: $profile->min_rate_cents,
            targetCountries: $profile->target_countries,
            weights: $profile->weights,
            stale: self::isStale($profile, $cvs, $userId),
            updatedAt: $profile->updated_at?->toIso8601String(),
        );
    }

    private static function isStale(ScoutProfileEloquentModel $profile, CvSourcePort $cvs, int $userId): bool
    {
        if ($profile->source_cv_uuid === null) {
            return false;
        }

        $current = $cvs->cvForUser($profile->source_cv_uuid, $userId);

        return $current === null || $current->contentHash !== $profile->cv_hash;
    }
}
