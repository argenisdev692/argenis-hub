<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Domain\Ports\ProfileRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\ProfileMapper;

final readonly class EloquentProfileRepository implements ProfileRepositoryPort
{
    public function current(?int $userId = null): ?Profile
    {
        $model = ScoutProfileEloquentModel::query()
            ->where('is_current', true)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('id')
            ->first();

        return $model === null ? null : ProfileMapper::toEntity($model);
    }

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
    ): Profile {
        return DB::transaction(static function () use (
            $userId, $sourceCvUuid, $cvHash, $confirmedSkills, $potentialSkills,
            $proofPoints, $languages, $minRateCents, $targetCountries, $weights,
        ): Profile {
            ScoutProfileEloquentModel::query()
                ->where('user_id', $userId)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $version = (int) (ScoutProfileEloquentModel::query()->where('user_id', $userId)->max('version') ?? 0) + 1;

            return ProfileMapper::toEntity(ScoutProfileEloquentModel::query()->create([
                'user_id' => $userId,
                'version' => $version,
                'source_cv_uuid' => $sourceCvUuid,
                'cv_hash' => $cvHash,
                'confirmed_skills' => $confirmedSkills,
                'potential_skills' => $potentialSkills,
                'proof_points' => $proofPoints,
                'languages' => $languages,
                'min_rate_cents' => $minRateCents,
                'target_countries' => $targetCountries,
                'weights' => $weights,
                'is_current' => true,
            ]));
        });
    }
}
