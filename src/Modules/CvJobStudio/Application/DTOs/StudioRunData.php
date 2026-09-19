<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A discovery run's progress (Runs page, polled). Allowlist: no internal ids,
 * no query payloads, no profile rules (OWASP §12). `status` is one of
 * queued, harvesting, harvested, extracted, finished or failed.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioRunData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly int $candidatesCount,
        public readonly int $gatePassedCount,
        public readonly int $extractedCount,
        public readonly int $scoredCount,
        public readonly int $newMatchesCount,
        public readonly int $spendMicros,
    ) {}
}
