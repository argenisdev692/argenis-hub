<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Entities\Profile;

interface ProfileRepositoryPort
{
    /**
     * The current version for a user, or the newest current version of any
     * user when `$userId` is null (scheduled scoring has no request user).
     */
    public function current(?int $userId = null): ?Profile;

    /**
     * Retires the user's current version and stores the next one, atomically.
     *
     * @param  list<string>  $confirmedSkills
     * @param  list<string>  $potentialSkills
     * @param  list<array<string, mixed>>  $proofPoints
     * @param  array<string, string>  $languages
     * @param  list<string>|null  $targetCountries
     * @param  array<string, mixed>|null  $weights
     */
    public function publishVersion(
        int $userId,
        ?string $sourceCvUuid,
        ?string $cvHash,
        array $confirmedSkills,
        array $potentialSkills,
        array $proofPoints,
        array $languages,
        ?int $minRateCents = null,
        ?array $targetCountries = null,
        ?array $weights = null,
    ): Profile;
}
