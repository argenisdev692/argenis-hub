<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SubmitMetricAnswersData extends Data
{
    /** @param  array<string, string>  $answers */
    public function __construct(public readonly array $answers) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return ['answers' => ['required', 'array', 'max:6'], 'answers.*' => ['string', 'max:2000']];
    }
}
