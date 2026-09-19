<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\UpdateProfileData;
use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;

/**
 * Operator tuning (spec US-1 CA-6): every save mints a new version carrying
 * the previous derivation forward. Scores keep pointing at the version used.
 */
final readonly class UpdateProfileHandler
{
    public function __construct(private ProfileRepositoryPort $profiles) {}

    public function handle(UpdateProfileData $data, int $userId): Profile
    {
        $current = $this->profiles->current($userId) ?? throw new ProfileNotFoundException;

        return $this->profiles->publishVersion(
            userId: $userId,
            sourceCvUuid: $current->sourceCvUuid,
            cvHash: $current->cvHash,
            confirmedSkills: $current->confirmedSkills,
            potentialSkills: $current->potentialSkills,
            proofPoints: $current->proofPoints,
            languages: $data->languages ?? $current->languages,
            minRateCents: $data->minRateCents ?? $current->minRateCents,
            targetCountries: $data->targetCountries ?? $current->targetCountries,
            weights: $data->weights ?? $current->weights,
        );
    }
}
