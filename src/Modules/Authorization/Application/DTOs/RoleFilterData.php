<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared role list filter — consumed by ListRolesHandler and RoleExportController
 * through the single `Role::scopeApplyFilters()` (BACKEND-PHP §4.1, no duplicated
 * `when()` chains). Inherits the search/status/date shape from
 * {@see SoftDeleteFilterData}; `status`: active | suspended (soft-deleted).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class RoleFilterData extends SoftDeleteFilterData
{
    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the role `name`; the date window narrows on `created_at`.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // Lifecycle. `active` (and omitting this) returns live roles;
            // `suspended` returns only the soft-deleted ones.
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
