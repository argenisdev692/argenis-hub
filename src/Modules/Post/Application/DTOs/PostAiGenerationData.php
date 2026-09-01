<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Enums\PostImageMode;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Persistence\Eloquent\Models\PostAiGenerationEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What the wizard polls: where a background generation is, and — once it is
 * {@see PostAiGenerationStatus::Completed} — the draft itself.
 *
 * `label` and `maxIterations` are derived server-side rather than duplicated in
 * the client: the phase names belong with the enum that defines the phases,
 * and the iteration ceiling belongs with
 * {@see PostContentQualityEvaluator::MAX_ITERATIONS}, which is the only place
 * allowed to change it.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PostAiGenerationData extends Data
{
    public function __construct(
        public string $uuid,
        public string $topic,
        public string $provider,
        public PostImageMode $imageMode,
        public PostAiGenerationStatus $status,
        public string $label,
        public ?string $stageMessage,
        public int $progress,
        public int $iteration,
        public int $maxIterations,
        public bool $isTerminal,
        public ?GeneratedPostContentData $result,
        public ?string $errorMessage,
    ) {}

    #[\NoDiscard]
    public static function fromModel(PostAiGenerationEloquentModel $generation): self
    {
        return new self(
            uuid: $generation->uuid,
            topic: $generation->topic,
            provider: $generation->provider,
            imageMode: $generation->image_mode,
            status: $generation->status,
            label: $generation->status->label(),
            stageMessage: $generation->stage_message,
            progress: $generation->progress,
            iteration: $generation->iteration,
            maxIterations: PostContentQualityEvaluator::MAX_ITERATIONS,
            isTerminal: $generation->status->isTerminal(),
            result: $generation->result !== null
                ? GeneratedPostContentData::from($generation->result)
                : null,
            errorMessage: $generation->error_message,
        );
    }
}
