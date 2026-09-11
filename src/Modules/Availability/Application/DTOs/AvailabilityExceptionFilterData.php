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
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * Unlike the other list filters, the date window here narrows on the
     * exception's own date, not on `created_at` — this endpoint answers "what
     * is open in June", not "what was entered in June".
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            // Free-text match on the reason recorded for the exception.
            'search' => ['nullable', 'string', 'max:255'],
            // Which kind of override: `open` for forced-open days, `closed` for
            // closures. Omit for both.
            'availability' => ['nullable', 'string', 'in:open,closed'],
            // Lifecycle. `active` (and omitting this) returns live exceptions;
            // `suspended` returns only the soft-deleted ones.
            'status' => ['nullable', 'string', 'in:active,suspended'],
            // Inclusive first day of the period to inspect, `YYYY-MM-DD`.
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            // Inclusive last day of the period. May not fall before `date_from`.
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
