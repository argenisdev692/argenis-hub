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
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
