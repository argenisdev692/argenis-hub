<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Application\DTOs\UpdateProfileData;
use Modules\LeadScout\Domain\Exceptions\ProfileNotFoundException;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutProfileEloquentModel;

/**
 * Operator tuning (spec US-1 CA-6): every save mints a new version carrying
 * the previous derivation forward. Scores keep pointing at the version used.
 */
final readonly class UpdateProfileHandler
{
    public function handle(UpdateProfileData $data, int $userId): ScoutProfileEloquentModel
    {
        $current = ScoutProfileEloquentModel::query()
            ->where('user_id', $userId)
            ->where('is_current', true)
            ->first() ?? throw new ProfileNotFoundException;

        return DB::transaction(function () use ($current, $data, $userId): ScoutProfileEloquentModel {
            $current->update(['is_current' => false]);

            return ScoutProfileEloquentModel::query()->create([
                'user_id' => $userId,
                'version' => $current->version + 1,
                'source_cv_uuid' => $current->source_cv_uuid,
                'cv_hash' => $current->cv_hash,
                'confirmed_skills' => $current->confirmed_skills,
                'potential_skills' => $current->potential_skills,
                'proof_points' => $current->proof_points,
                'languages' => $data->languages ?? $current->languages,
                'min_rate_cents' => $data->minRateCents ?? $current->min_rate_cents,
                'target_countries' => $data->targetCountries ?? $current->target_countries,
                'weights' => $data->weights ?? $current->weights,
                'is_current' => true,
            ]);
        });
    }
}
