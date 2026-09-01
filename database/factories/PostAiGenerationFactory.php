<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Enums\PostImageMode;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;

/**
 * @extends Factory<PostAiGenerationEloquentModel>
 */
final class PostAiGenerationFactory extends Factory
{
    protected $model = PostAiGenerationEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'topic' => fake()->sentence(5),
            'angle' => null,
            'key_trend' => null,
            'provider' => 'openai',
            'image_mode' => PostImageMode::Full->value,
            'status' => PostAiGenerationStatus::Queued->value,
            'stage_message' => 'Queued — waiting for a worker.',
            'progress' => 0,
            'iteration' => 0,
            'result' => null,
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Mid-flight, on the judging phase of iteration 2 — the shape the wizard
     * polls most often.
     */
    public function running(): self
    {
        return $this->state(fn (): array => [
            'status' => PostAiGenerationStatus::Judging->value,
            'stage_message' => 'Iteration 2: an independent model is scoring the draft…',
            'progress' => 44,
            'iteration' => 2,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function completed(array $result = []): self
    {
        return $this->state(fn (): array => [
            'status' => PostAiGenerationStatus::Completed->value,
            'stage_message' => 'Draft ready — all scores passed.',
            'progress' => 100,
            'iteration' => 1,
            'result' => $result,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $message = 'Post content generation failed on every iteration.'): self
    {
        return $this->state(fn (): array => [
            'status' => PostAiGenerationStatus::Failed->value,
            'stage_message' => null,
            'error_message' => $message,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
