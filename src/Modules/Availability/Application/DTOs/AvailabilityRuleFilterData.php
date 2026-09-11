<?php

declare(strict_types=1);

namespace Modules\Availability\Application\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * List filter for weekly rules — consumed by ListAvailabilityRulesHandler via the
 * single `AvailabilityRuleEloquentModel::scopeApplyFilters()`. `status` toggles
 * soft-delete state (active | suspended), applied at the repository via
 * `onlyTrashed()`; `availability` narrows by the `is_available` flag.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityRuleFilterData extends Data
{
    public function __construct(
        public ?int $dayOfWeek = null,
        public ?string $availability = null,
        public ?string $status = null,
    ) {}

    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * There is no `search` or date window here: these are the seven rows of a
     * weekly template, not a growing list.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            // Day of the week, 0 = Sunday through 6 = Saturday.
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            // Whether the rule opens or closes that day. Omit for both.
            'availability' => ['nullable', 'string', 'in:available,unavailable'],
            // Lifecycle. `active` (and omitting this) returns live rules;
            // `suspended` returns only the soft-deleted ones.
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
