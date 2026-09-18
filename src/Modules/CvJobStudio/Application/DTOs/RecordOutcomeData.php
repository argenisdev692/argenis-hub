<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class RecordOutcomeData extends Data
{
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'in:unknown,pending,no_reply,rejected,screening,interview,offer,withdrawn'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
