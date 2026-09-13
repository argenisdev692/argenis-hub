<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class BibleCharacterData extends Data
{
    public function __construct(
        #[Required, Max(120)]
        public string $name,
        #[Max(200)]
        public string $role = '',
        #[Max(60)]
        public ?string $organisationKey = null,
    ) {}
}
