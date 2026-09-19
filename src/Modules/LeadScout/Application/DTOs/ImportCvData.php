<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Import request: the CV uuid only. Ownership is enforced server-side
 * against the authenticated user (`Rule::exists` scoped in the handler —
 * a foreign uuid behaves as 404, never as a leak, OWASP §11).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ImportCvData extends Data
{
    public function __construct(
        #[Required, Uuid]
        public string $cvUuid,
    ) {}
}
