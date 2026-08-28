<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models\PortfolioEloquentModel;

/**
 * @extends Factory<PortfolioEloquentModel>
 */
final class PortfolioFactory extends Factory
{
    protected $model = PortfolioEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = ucfirst((string) fake()->unique()->words(3, true));

        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'title' => $title,
            'client_name' => fake()->company(),
            'project_type' => fake()->randomElement(['Web App', 'Landing Page', 'E-commerce', 'Mobile App', 'API', 'Branding']),
            'tech_stack' => fake()->randomElements(
                ['React', 'Next.js', 'Laravel', 'Vue', 'PostgreSQL', 'Stripe', 'Tailwind', 'Astro'],
                fake()->numberBetween(2, 4),
            ),
            'live_url' => 'https://'.fake()->domainName(),
            'published_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
            'is_public' => true,
            'cover_path' => 'portfolios/'.Str::uuid7().'/cover.jpg',
            'video_path' => null,
            'description' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }

    public function hidden(): self
    {
        return $this->state(fn (): array => ['is_public' => false]);
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function scheduled(): self
    {
        return $this->state(fn (): array => ['published_at' => fake()->dateTimeBetween('+1 day', '+1 month')]);
    }
}
