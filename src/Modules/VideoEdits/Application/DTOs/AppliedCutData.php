<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditAppliedCutEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class AppliedCutData extends Data
{
    /**
     * @param  list<string>  $reasons
     * @param  list<string>  $origins
     */
    public function __construct(
        public int $sequence,
        public int $startMs,
        public int $endMs,
        public int $durationMs,
        public array $reasons,
        public array $origins,
    ) {}

    public static function fromModel(VideoEditAppliedCutEloquentModel $cut): self
    {
        return new self(
            sequence: $cut->sequence,
            startMs: $cut->start_ms,
            endMs: $cut->end_ms,
            durationMs: $cut->end_ms - $cut->start_ms,
            reasons: array_values($cut->reasons),
            origins: array_values($cut->origins),
        );
    }
}
