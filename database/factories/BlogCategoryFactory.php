<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Blog\Infrastructure\Persistence\Eloquent\Models\BlogCategoryEloquentModel;

/**
 * @extends Factory<BlogCategoryEloquentModel>
 */
final class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategoryEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'blog_category_name' => fake()->unique()->words(2, true),
            'blog_category_description' => fake()->sentence(),
            'blog_category_image' => null,
            'user_id' => User::factory(),
        ];
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }

    public function withImage(string $key = 'blog-categories/example.webp'): self
    {
        return $this->state(fn (): array => ['blog_category_image' => $key]);
    }
}
