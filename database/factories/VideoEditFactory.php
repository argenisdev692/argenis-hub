<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * @extends Factory<VideoEditEloquentModel>
 */
final class VideoEditFactory extends Factory
{
    protected $model = VideoEditEloquentModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'previous_edit_id' => null,
            'mode' => VideoEditMode::AutoEdit,
            'status' => VideoEditStatus::Draft,
            'parameters' => [
                'silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.0],
                'manual_ranges' => [],
            ],
            'progress_percent' => 0,
            'attempts' => 0,
        ];
    }

    public function merge(): self
    {
        return $this->state(fn (): array => [
            'mode' => VideoEditMode::Merge,
            'parameters' => ['silence_removal' => ['enabled' => false], 'manual_ranges' => []],
        ]);
    }

    public function queued(): self
    {
        return $this->state(fn (): array => [
            'status' => VideoEditStatus::Queued,
            'queued_at' => now(),
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn (): array => [
            'status' => VideoEditStatus::Processing,
            'current_stage' => ProcessingStage::Render,
            'progress_percent' => 40,
            'attempts' => 1,
            'queued_at' => now()->subMinutes(2),
            'started_at' => now()->subMinute(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VideoEditStatus::Completed,
            'current_stage' => ProcessingStage::Publish,
            'progress_percent' => 100,
            'attempts' => 1,
            'original_duration_ms' => 600_000,
            'final_duration_ms' => 540_000,
            'removed_duration_ms' => 60_000,
            'applied_cut_count' => 12,
            'result_path' => 'video-edits/tests/'.$attributes['uuid'].'/result.mp4',
            'result_size_bytes' => 250_000_000,
            'queued_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(19),
            'completed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => VideoEditStatus::Failed,
            'attempts' => 3,
            'failure_code' => 'processing_error',
            'failure_message' => 'The video could not be processed.',
            'queued_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(9),
            'failed_at' => now(),
            'sources_expire_at' => now()->addHours(24),
        ]);
    }

    public function failedWithExpiredSources(): self
    {
        return $this->failed()->state(fn (): array => [
            'failed_at' => now()->subHours(25),
            'sources_expire_at' => now()->subHour(),
        ]);
    }

    public function invalidCutRanges(): self
    {
        return $this->failed()->state(fn (): array => [
            'attempts' => 1,
            'failure_code' => 'invalid_cut_ranges',
            'failure_message' => 'Some manual ranges fall outside the video.',
            'failure_details' => [
                'manual_ranges' => [
                    ['index' => 0, 'error' => 'end_beyond_duration'],
                ],
            ],
        ]);
    }
}
