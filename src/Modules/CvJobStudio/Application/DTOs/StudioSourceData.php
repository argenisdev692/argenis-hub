<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** One row of the discovery-source catalogue (access mode + health). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioSourceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $kind,
        public readonly int $tier,
        public readonly string $layer,
        public readonly string $status,
        public readonly ?string $healthCheckedAt,
        public readonly bool $attributionRequired,
        public readonly string $accessMode,
        public readonly int $resolutionTier,
    ) {}
}
