<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** A proposed skill relation awaiting confirm/reject (relations inbox). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioSkillRelationData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $fromSkill,
        public readonly string $toSkill,
        public readonly string $kind,
        public readonly string $origin,
        public readonly string $status,
        public readonly ?string $createdAt,
    ) {}
}
