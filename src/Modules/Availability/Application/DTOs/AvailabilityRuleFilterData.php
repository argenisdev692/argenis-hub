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
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'availability' => ['nullable', 'string', 'in:available,unavailable'],
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
