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
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', Rule::in([
                'draft', 'generating', 'ready', 'needs_review', 'published', 'scheduled', 'suspended',
            ])],
        ];
    }
}
