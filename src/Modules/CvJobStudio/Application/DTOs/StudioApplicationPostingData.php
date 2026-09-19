<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** The posting summary embedded in an application card. */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioApplicationPostingData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly ?string $employerName,
        public readonly string $status,
    ) {}
}
