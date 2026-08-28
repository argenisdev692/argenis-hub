<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioMediaEloquentModel;

/**
 * @extends Factory<PortfolioMediaEloquentModel>
 */
final class PortfolioMediaFactory extends Factory
{
    protected $model = PortfolioMediaEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'portfolio_id' => PortfolioEloquentModel::factory(),
            'path' => 'portfolios/'.Str::uuid7().'/gallery/'.fake()->numberBetween(1, 9).'.jpg',
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
