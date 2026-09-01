<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\SocialMedia\Application\DTOs\SocialMediaContentFilterData;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared list filter — consumed by ListCampaignsHandler via the single
 * `CampaignEloquentModel::scopeApplyFilters()` (BACKEND-PHP §4.1/§5.2).
 * `status` folds the campaign lifecycle AND the soft-delete state into one
 * axis, mirroring
 * {@see SocialMediaContentFilterData}.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignFilterData extends SoftDeleteFilterData
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
