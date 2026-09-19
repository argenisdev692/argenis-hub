<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The candidate's own response rate for one channel bucket, with its 90%
 * credible interval and whether the evidence gate is passed (FR-48, NFR-14).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioOwnRateData extends Data
{
    public function __construct(
        public readonly string $bucket,
        public readonly int $applications,
        public readonly int $positives,
        public readonly ?float $rate,
        public readonly float $lower,
        public readonly float $upper,
        public readonly bool $gatePassed,
    ) {}
}
