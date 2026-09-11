<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Source metadata shown to the owner. The object path never leaves the
 * backend — `available` only says whether the file is still retained.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditSourceData extends Data
{
    public function __construct(
        public string $uuid,
        public int $position,
        public string $originalName,
        public ?int $durationMs,
        public ?int $width,
        public ?int $height,
        public ?bool $hasAudio,
        public bool $available,
    ) {}

    public static function fromModel(VideoEditSourceEloquentModel $source): self
    {
        return new self(
            uuid: $source->uuid,
            position: $source->position,
            originalName: $source->original_name,
            durationMs: $source->duration_ms,
            width: $source->width,
            height: $source->height,
            hasAudio: $source->has_audio,
            available: $source->storage_path !== null,
        );
    }
}
