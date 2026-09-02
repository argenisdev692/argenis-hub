<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Shared\Domain\Enums\BillingUnit;

/**
 * @extends Factory<ProductEloquentModel>
 */
final class ProductFactory extends Factory
{
    protected $model = ProductEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst((string) fake()->unique()->words(3, true));

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'client_id' => null,
            'type' => ProductType::Course,
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->sentence(),
            'price' => 52.00,
            'currency' => 'EUR',
            'default_unit' => BillingUnit::Hour,
            'status' => ProductStatus::Published,
            'level' => 'beginner',
            'language' => 'es',
            'total_hours' => 25.00,
            'total_sessions' => 5,
            'modality' => 'online',
        ];
    }

    public function videoCourse(): self
    {
        return $this->state(fn (): array => [
            'type' => ProductType::VideoCourse,
            'default_unit' => BillingUnit::Hour,
            'total_sessions' => null,
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Draft]);
    }
}
