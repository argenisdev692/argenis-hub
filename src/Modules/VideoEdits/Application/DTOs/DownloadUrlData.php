<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A short-lived signed link to the result (E5 · D12). Never stored or logged.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class DownloadUrlData extends Data
{
    public function __construct(
        public string $url,
        public string $expiresAt,
    ) {}
}
