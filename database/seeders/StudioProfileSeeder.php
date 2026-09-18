<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/**
 * Seeds the fullstack profile only (T-018, CHG-6/Q11): gates, stack lock,
 * geography, languages, seniority band, protected block, candidate baseline
 * and the rules v2 snapshot from `config/cv-job-studio.php`. A second
 * profile is data, never schema (SC-7 — covered by fixture in tests).
 */
class StudioProfileSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->command->warn('StudioProfileSeeder: no user found, skipping.');

            return;
        }

        StudioProfileEloquentModel::query()->firstOrCreate(
            ['user_id' => $user->id, 'slug' => 'fullstack'],
            [
                'uuid' => (string) Str::uuid7(),
                'name' => 'Fullstack Remote',
                'flow' => 'fullstack',
                'is_active' => true,
                'base_city' => 'Lisbon',
                'base_country' => 'PT',
                'accepted_remote_scopes' => ['remote_global', 'remote_eu', 'remote_pt_es'],
                'stack_must' => ['Laravel', 'Vue.js'],
                'stack_reject' => ['WordPress', 'Angular', 'NestJS'],
                'never_seed' => ['NestJS', 'Angular'],
                'search_languages' => ['en', 'es', 'pt'],
                'geography_prefer' => ['PT', 'ES', 'EU'],
                'geography_deny' => [],
                'years_baseline' => 4,
                'education_level' => 'bachelor',
                'language_levels' => ['es' => 'native', 'en' => 'c1', 'pt' => 'b2'],
                'protected_block' => [],
                'seniority_band' => ['min' => 'mid', 'max' => 'senior'],
                'tone' => 'direct',
                'rules' => config('cv-job-studio'),
                'rules_version' => (int) config('cv-job-studio.rules_version', 2),
            ],
        );
    }
}
