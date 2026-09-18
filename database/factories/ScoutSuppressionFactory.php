<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

/**
 * @extends Factory<ScoutSuppressionEloquentModel>
 */
final class ScoutSuppressionFactory extends Factory
{
    protected $model = ScoutSuppressionEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'canonical_domain' => fake()->unique()->domainName(),
            'source' => SuppressionSource::Manual,
            'reason' => 'Operator request',
        ];
    }

    public function forDomain(string $domain): self
    {
        return $this->state(fn (): array => ['canonical_domain' => $domain]);
    }
}
