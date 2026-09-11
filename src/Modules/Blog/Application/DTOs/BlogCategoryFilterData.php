<?php

declare(strict_types=1);

namespace Modules\Blog\Application\DTOs;

use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared list filter — consumed by ListBlogCategoriesHandler via the single
 * `BlogCategoryEloquentModel::scopeApplyFilters()` (BACKEND-PHP §4.1 — no
 * duplicated `when()` chains). Inherits the search/status/date shape from
 * {@see SoftDeleteFilterData}; `status`: active | suspended (soft-deleted).
 *
 * `#[MapOutputName]` snake-cases the payload (`date_from` / `date_to`) so the
 * DTO round-trips into the Inertia `filters` prop under the frontend contract.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class BlogCategoryFilterData extends SoftDeleteFilterData
{
    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the category name or its description; the date window
     * narrows on `created_at`.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // Lifecycle. `active` (and omitting this) returns live categories;
            // `suspended` returns only the soft-deleted ones.
            'status' => ['nullable', 'string', 'in:active,suspended'],
        ];
    }
}
