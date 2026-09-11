<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SilenceRemovalData extends Data
{
    public function __construct(
        public bool $enabled,
        public ?float $thresholdSeconds = null,
    ) {}
}
