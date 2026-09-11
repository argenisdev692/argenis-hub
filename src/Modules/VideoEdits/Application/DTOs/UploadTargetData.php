<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Where the browser PUTs one clip (AD-1). Returned once, on create; never stored or logged.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class UploadTargetData extends Data
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $sourceUuid,
        public int $position,
        public string $uploadUrl,
        public array $headers,
        public string $expiresAt,
    ) {}
}
