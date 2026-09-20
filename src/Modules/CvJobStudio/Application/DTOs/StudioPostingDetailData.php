<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Match-report props for one posting: the stored breakdown explains the
 * total without recomputing (SC-2). Full JD text never crosses this boundary.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioPostingDetailData extends Data
{
    /**
     * @param  list<array{uuid: string, canonical_name: string, tag: string, nature: string}>  $requirements
     * @param  list<array{gate_code: string, passed: bool, reason_code: string|null}>  $gates
     */
    public function __construct(
        public readonly StudioPostingData $posting,
        public readonly array $requirements,
        public readonly array $gates,
        public readonly ?StudioScoreData $score,
    ) {}
}
