<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared permission list filter — consumed by ListPermissionsHandler and
 * PermissionExportController through the single `Permission::scopeApplyFilters()`
 * (BACKEND-PHP §4.1). Inherits the search/status/date shape from
 * {@see SoftDeleteFilterData}; `status`: active | suspended (soft-deleted).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class PermissionFilterData extends SoftDeleteFilterData
{
    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the permission `name`; the date window narrows on
     * `created_at`.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // Lifecycle. `active` (and omitting this) returns live permissions;
            // `suspended` returns only the soft-deleted ones.
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
