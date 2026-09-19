<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One application card (Applications board): candidate-side status and
 * employer-side outcome kept apart (FR-25). No internal ids (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioApplicationData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly string $outcome,
        public readonly ?string $appliedAt,
        public readonly ?string $outcomeAt,
        public readonly ?StudioApplicationPostingData $posting,
    ) {}
}
