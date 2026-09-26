<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProfileEloquentModel;

/**
 * Seeds the operator's fullstack profile (T-018, CHG-6/Q11): gates, stack
 * lock, geography, languages, seniority band, protected block, candidate
 * baseline and the rules v2 snapshot from `config/cv-job-studio.php`.
 * Values mirror the operator's CV (Argenis Gonzalez — Full Stack
 * Laravel + Inertia, React and Vue, remote from Covilha, PT) so a fresh seed
 * needs no manual re-registration. A second profile is data, never schema
 * (SC-7 — covered by fixture in tests).
 *
 * React sits in `stack_must` on purpose. `GateEvaluator::gateStackLock()` passes
 * a posting on the FIRST term it finds, so the list is an OR and adding a term
 * widens the gate instead of narrowing it — React postings that never mention
 * PHP were being discarded before. `RefreshVocabularyHandler` seeds the search
 * vocabulary from the same list, so without React the pipeline was never
 * looking for React work in the first place.
 *
 * The slug stays `fullstack-laravel-vue-remote` even though the stack has
 * moved on: it is this seed row's identity in `firstOrCreate()`, and renaming it
 * would create a second profile on every existing database. The slug is a
 * stable key, the `name` is what a human reads.
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

        $slug = 'fullstack-laravel-vue-remote';

        $attributes = [
            'uuid' => (string) Str::uuid7(),
            'name' => 'Fullstack · Laravel + Inertia + React (Remote)',
            'flow' => 'fullstack',
            'is_active' => true,
            'base_city' => 'Covilha',
            'base_country' => 'PT',
            'accepted_remote_scopes' => ['remote_global', 'remote_eu', 'remote_pt_es'],
            'stack_must' => ['PHP', 'Laravel', 'Inertia.js', 'React', 'Vue.js', 'TypeScript', 'MySQL', 'PostgreSQL', 'Redis', 'Tailwind CSS', 'REST API', 'AWS'],
            'stack_reject' => ['.NET', 'Angular', 'Spring Boot', 'WordPress'],
            'never_seed' => ['.NET', 'Angular', 'Spring Boot', 'WordPress'],
            'search_languages' => ['en', 'es', 'pt'],
            'geography_prefer' => ['PT', 'ES', 'EU'],
            'geography_deny' => [],
            'years_baseline' => 4,
            'education_level' => 'bachelor',
            'language_levels' => ['es' => 'native', 'pt' => 'b2', 'en' => 'b1'],
            'protected_block' => [],
            'seniority_band' => ['min' => 'mid', 'max' => 'senior'],
            'tone' => 'direct',
            'rules' => config('cv-job-studio'),
            'rules_version' => (int) config('cv-job-studio.rules_version', 2),
        ];

        $profile = StudioProfileEloquentModel::query()->firstOrCreate(
            ['user_id' => $user->id, 'slug' => $slug],
            $attributes,
        );

        // `firstOrCreate` leaves an existing row untouched, so a seeder run on a
        // live database would never deliver the React addition above. Converging
        // only this row's stack and name keeps the seed authoritative without
        // ever being able to create a second profile.
        if ($profile->wasRecentlyCreated === false) {
            $profile->forceFill([
                'name' => $attributes['name'],
                'stack_must' => $attributes['stack_must'],
                'stack_reject' => $attributes['stack_reject'],
                'never_seed' => $attributes['never_seed'],
                'search_languages' => $attributes['search_languages'],
                'years_baseline' => $attributes['years_baseline'],
            ])->save();
        }
    }
}
