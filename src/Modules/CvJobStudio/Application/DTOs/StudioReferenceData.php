<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** An unresolved link_only/resolve_only signal the candidate opens manually (FR-53). */
#[MapOutputName(SnakeCaseMapper::class)]
final class StudioReferenceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly ?string $employerName,
        public readonly string $canonicalUrl,
        public readonly ?string $source,
        public readonly ?string $createdAt,
    ) {}
}
