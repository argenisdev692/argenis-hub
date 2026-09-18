<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class PasteJobTextData extends Data
{
    public function __construct(public readonly string $text) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['text' => ['required', 'string', 'min:50', 'max:100000']];
    }
}
