<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** Which CV to parse — the primary CV when `cv_uuid` is omitted. */
#[MapInputName(SnakeCaseMapper::class)]
final class SelectCvData extends Data
{
    public function __construct(public readonly ?string $cvUuid = null) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['cv_uuid' => ['nullable', 'string', 'uuid']];
    }
}
