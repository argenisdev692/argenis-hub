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
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the campaign topic or headline; the date window narrows
     * on `created_at`.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            // One parameter, two axes. The six workflow values narrow to that
            // stage of the campaign lifecycle, while `suspended` instead
            // returns the soft-deleted campaigns — which are excluded from
            // every other value. Omit it for all live campaigns.
            'status' => ['nullable', 'string', Rule::in([
                'draft', 'generating', 'ready', 'needs_review', 'published', 'scheduled', 'suspended',
            ])],
        ];
    }
}
