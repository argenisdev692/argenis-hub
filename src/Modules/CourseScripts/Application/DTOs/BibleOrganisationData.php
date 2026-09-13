<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A fictional organisation of the course (FR-9, D18). One is primary — the
 * company the audience works in; the rest are clients, suppliers, partners.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class BibleOrganisationData extends Data
{
    public function __construct(
        #[Required, Max(60)]
        public string $key,
        #[Required, Max(160)]
        public string $name,
        #[Max(200)]
        public string $role = '',
        #[Max(120)]
        public string $sector = '',
        public bool $isPrimary = false,
    ) {}
}
