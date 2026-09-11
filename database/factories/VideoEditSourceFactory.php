<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;

/**
 * @extends Factory<VideoEditSourceEloquentModel>
 */
final class VideoEditSourceFactory extends Factory
{
    protected $model = VideoEditSourceEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid7();

        return [
            'uuid' => $uuid,
            'video_edit_id' => VideoEditEloquentModel::factory(),
            'position' => 1,
            'original_name' => 'take-'.fake()->numberBetween(1, 9).'.mp4',
            'extension' => 'mp4',
            'declared_mime' => 'video/mp4',
            'declared_size_bytes' => fake()->numberBetween(10_000_000, 900_000_000),
            'storage_path' => 'video-edits/tests/sources/'.$uuid.'.mp4',
        ];
    }

    public function probed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'size_bytes' => $attributes['declared_size_bytes'],
            'sha256' => hash('sha256', (string) $attributes['uuid']),
            'duration_ms' => 300_000,
            'width' => 1920,
            'height' => 1080,
            'frame_rate' => '30.000',
            'has_audio' => true,
            'container' => 'mov,mp4,m4a,3gp,3g2,mj2',
            'video_codec' => 'h264',
            'audio_codec' => 'aac',
        ]);
    }

    public function purged(): self
    {
        return $this->state(fn (): array => ['storage_path' => null]);
    }
}
