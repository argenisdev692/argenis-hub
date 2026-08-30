<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Post\Domain\Enums\PostStatus;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostEloquentModel;

/**
 * @extends Factory<PostEloquentModel>
 */
final class PostFactory extends Factory
{
    protected $model = PostEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'uuid' => (string) Str::uuid7(),
            'post_title' => $title,
            'post_title_slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'post_content' => fake()->paragraphs(3, true),
            'post_excerpt' => fake()->sentence(12),
            'post_cover_image' => null,
            'meta_title' => null,
            'meta_description' => null,
            'meta_keywords' => null,
            'category_id' => null,
            'user_id' => null,
            'post_status' => PostStatus::Draft->value,
            'scheduled_at' => null,
            'published_at' => null,
            'is_ai_generated' => false,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'post_status' => PostStatus::Published->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'post_status' => PostStatus::Draft->value,
            'published_at' => null,
        ]);
    }

    public function scheduled(): self
    {
        return $this->state(fn (): array => [
            'post_status' => PostStatus::Scheduled->value,
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 14)),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
