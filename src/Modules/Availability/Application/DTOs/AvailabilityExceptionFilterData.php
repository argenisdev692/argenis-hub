<?php

declare(strict_types=1);

namespace Modules\Availability\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * List filter for date exceptions. `status` toggles soft-delete state
 * (active | suspended), applied at the repository via `onlyTrashed()`;
 * `availability` narrows open vs closed; `date_from`/`date_to` bound the period.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityExceptionFilterData extends Data
{
    public function __construct(
        public ?string $availability = null,
        public ?string $status = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'availability' => ['nullable', 'string', 'in:open,closed'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
