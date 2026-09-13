<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Outcome of a bulk delete. `skipped` counts rows that were still processing
 * (D14) or no longer belong to the caller — the toast reports both honestly.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BulkDeletedVideoEditsData extends Data
{
    public function __construct(
        public int $deleted,
        public int $skipped,
    ) {}
}
