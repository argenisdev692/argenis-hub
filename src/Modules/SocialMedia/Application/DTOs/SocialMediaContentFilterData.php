<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Post\Application\DTOs\PostFilterData;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared list filter — consumed by ListSocialMediaContentHandler via the
 * single `SocialMediaContentEloquentModel::scopeApplyFilters()` (BACKEND-PHP
 * §4.1/§5.2). `status` folds the content lifecycle AND the soft-delete state
 * into one axis, mirroring {@see PostFilterData}.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SocialMediaContentFilterData extends SoftDeleteFilterData
{
    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the content topic or headline; the date window narrows
     * on `created_at`.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // One parameter, two axes. The six workflow values narrow to that
            // stage of the content lifecycle, while `suspended` instead returns
            // the soft-deleted items — which are excluded from every other
            // value. Omit it for all live content.
            'status' => ['nullable', 'string', Rule::in([
                'draft', 'generating', 'ready', 'needs_review', 'published', 'scheduled', 'suspended',
            ])],
        ];
    }
}
