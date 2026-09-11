<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * User-safe failure information only — never commands, paths or stderr (FR-20).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditFailureData extends Data
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?array $details,
    ) {}
}
