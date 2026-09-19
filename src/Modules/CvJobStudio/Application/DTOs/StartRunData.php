<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/** Discovery run input: the profile whose gates and sources drive the run. */
#[MapInputName(SnakeCaseMapper::class)]
final class StartRunData extends Data
{
    public function __construct(public readonly string $profileUuid) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['profile_uuid' => ['required', 'string', 'uuid']];
    }
}
