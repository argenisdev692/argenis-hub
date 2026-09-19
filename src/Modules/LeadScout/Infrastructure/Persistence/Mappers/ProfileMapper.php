<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Profile;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

final readonly class ProfileMapper
{
    public static function toEntity(ScoutProfileEloquentModel $model): Profile
    {
        return new Profile(
            id: $model->id,
            uuid: $model->uuid,
            userId: (int) $model->user_id,
            version: (int) $model->version,
            sourceCvUuid: $model->source_cv_uuid,
            cvHash: $model->cv_hash,
            confirmedSkills: $model->confirmed_skills ?? [],
            potentialSkills: $model->potential_skills ?? [],
            proofPoints: $model->proof_points ?? [],
            languages: $model->languages ?? [],
            minRateCents: $model->min_rate_cents,
            targetCountries: $model->target_countries,
            weights: $model->weights,
            updatedAt: $model->updated_at?->toDateTimeImmutable(),
        );
    }
}
