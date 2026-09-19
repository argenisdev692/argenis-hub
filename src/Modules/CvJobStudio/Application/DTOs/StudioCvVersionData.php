<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** One generated CV version row (Versions page) — allowlist, no internal ids (OWASP §12). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioCvVersionData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $purpose,
        public readonly string $language,
        public readonly ?string $createdAt,
    ) {}
}
