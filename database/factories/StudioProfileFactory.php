<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/**
 * @extends Factory<StudioProfileEloquentModel>
 */
final class StudioProfileFactory extends Factory
{
    protected $model = StudioProfileEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'name' => 'Fullstack Remote',
            'slug' => 'fullstack-'.Str::lower(Str::random(6)),
            'flow' => 'fullstack',
            'is_active' => true,
            'base_city' => 'Lisbon',
            'base_country' => 'PT',
            'accepted_remote_scopes' => ['remote_global', 'remote_eu', 'remote_pt_es'],
            'stack_must' => ['Laravel', 'Vue.js'],
            'stack_reject' => ['WordPress'],
            'never_seed' => [],
            'search_languages' => ['en', 'es', 'pt'],
            'geography_prefer' => [],
            'geography_deny' => [],
            'years_baseline' => 4,
            'education_level' => 'bachelor',
            'language_levels' => ['es' => 'native', 'en' => 'c1', 'pt' => 'b2'],
            'protected_block' => [],
            'seniority_band' => ['min' => 'mid', 'max' => 'senior'],
            'tone' => 'direct',
            'rules' => config('cv-job-studio'),
            'rules_version' => 2,
        ];
    }
}
