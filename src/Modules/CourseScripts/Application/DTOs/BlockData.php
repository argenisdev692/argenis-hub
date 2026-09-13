<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class BlockData extends Data
{
    public function __construct(
        public string $uuid,
        public int $number,
        public string $title,
        public ?int $declaredDurationMinutes,
    ) {}

    public static function fromModel(CourseBlockEloquentModel $block): self
    {
        return new self(
            uuid: $block->uuid,
            number: $block->number,
            title: $block->title,
            declaredDurationMinutes: $block->declared_duration_minutes,
        );
    }
}
