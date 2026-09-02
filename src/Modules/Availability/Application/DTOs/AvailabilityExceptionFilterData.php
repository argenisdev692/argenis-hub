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
 * `availability` narrows open vs closed; `date_from`/`date_to` bound the period;
 * `search` matches the operator-written `reason`.
 *
 * `search` covers `reason` alone, because it is the only free-text column on the
 * table. The date stays on its own axis (`date_from`/`date_to`) rather than being
 * folded into the term, so "2026-11" narrows a period instead of matching a
 * string that happens to contain those digits.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityExceptionFilterData extends Data
{
    public function __construct(
        public ?string $search = null,
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
            'search' => ['nullable', 'string', 'max:255'],
            'availability' => ['nullable', 'string', 'in:open,closed'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
