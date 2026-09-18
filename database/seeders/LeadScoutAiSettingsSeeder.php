<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutAiSettingEloquentModel;

/**
 * AI defaults from `.env` (spec US-10, D3, T054): extraction on Gemini 3.7
 * Flash (Sonnet 5 fallback), drafts on Claude Sonnet 5 (Gemini fallback).
 * The web UI edits these rows afterwards — the seeder never overwrites.
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
                    'model' => $defaults['model'] ?? 'gemini-3.7-flash',
                    'fallback_provider' => $defaults['fallback_provider'] ?? null,
                    'fallback_model' => $defaults['fallback_model'] ?? null,
                ],
            );
        }
    }
}
