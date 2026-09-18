<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

/**
 * Source registry seed (spec US-2, T024): everything starts `paused` with
 * unreviewed terms — the operator reviews each source's ToS (OP-5) before
 * activating. Official/programmatic APIs outrank RSS (spec FR-3).
 */
class LeadScoutSourcesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->sources() as $attributes) {
            ScoutSourceEloquentModel::query()->firstOrCreate(
                ['name' => $attributes['name']],
                [...$attributes, 'uuid' => (string) Str::uuid7()],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            [
                'name' => 'ITJobs.pt',
                'type' => SourceType::JobApi->value,
                'country' => 'PT',
                'access_method' => 'api',
                'frequency_minutes' => 360,
                'priority' => 10,
                'status' => SourceStatus::Paused->value,
            ],
            [
                'name' => 'LandingJobs',
                'type' => SourceType::JobApi->value,
                'country' => null,
                'access_method' => 'api',
                'frequency_minutes' => 360,
                'priority' => 10,
                'status' => SourceStatus::Paused->value,
            ],
            [
                'name' => 'Arbeitnow',
                'type' => SourceType::JobApi->value,
                'country' => null,
                'access_method' => 'api',
                'frequency_minutes' => 360,
                'priority' => 10,
                'status' => SourceStatus::Paused->value,
            ],
            [
                'name' => 'LaraJobs',
                'type' => SourceType::Rss->value,
                'country' => null,
                'access_method' => 'rss',
                'frequency_minutes' => 360,
                'priority' => 5,
                'status' => SourceStatus::Paused->value,
            ],
            [
                // Remotive terms: at most 4 polls/day → 720 min floor respected.
                'name' => 'Remotive',
                'type' => SourceType::Rss->value,
                'country' => null,
                'access_method' => 'rss',
                'frequency_minutes' => 720,
                'priority' => 5,
                'status' => SourceStatus::Paused->value,
            ],
            [
                'name' => 'We Work Remotely',
                'type' => SourceType::Rss->value,
                'country' => null,
                'access_method' => 'rss',
                'frequency_minutes' => 360,
                'priority' => 5,
                'status' => SourceStatus::Paused->value,
            ],
        ];
    }
}
