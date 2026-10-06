<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

/**
 * AI defaults from `.env` (spec US-10, D3, T054). Create-once: the web UI edits
 * these rows afterwards and the seeder never overwrites them, so changing the
 * config default only affects a database that has not been seeded yet.
 */
class LeadScoutAiSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('lead-scout.ai_defaults', []) as $purpose => $defaults) {
            ScoutAiSettingEloquentModel::query()->firstOrCreate(
                ['purpose' => $purpose],
                [
                    'provider' => $defaults['provider'] ?? 'gemini',
                    'model' => $defaults['model'] ?? 'gemini-3.8-flash',
                    'fallback_provider' => $defaults['fallback_provider'] ?? null,
                    'fallback_model' => $defaults['fallback_model'] ?? null,
                ],
            );
        }
    }
}
