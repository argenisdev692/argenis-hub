<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

/**
 * @extends Factory<ScoutSourceEloquentModel>
 */
final class ScoutSourceFactory extends Factory
{
    protected $model = ScoutSourceEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'name' => ucfirst((string) fake()->words(2, true)),
            'type' => SourceType::Rss,
            'country' => null,
            'access_method' => 'rss',
            'frequency_minutes' => 360,
            'priority' => 0,
            'status' => SourceStatus::Paused,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (): array => [
            'status' => SourceStatus::Active,
            'terms_reviewed_at' => now(),
        ]);
    }
}
