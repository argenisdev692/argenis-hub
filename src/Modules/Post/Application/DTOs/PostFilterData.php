<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Shared\Application\DTOs\SoftDeleteFilterData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared list filter — consumed by ListPostsHandler via the single
 * `PostEloquentModel::scopeApplyFilters()` (BACKEND-PHP §4.1). `status` folds
 * the content lifecycle AND the soft-delete state into one axis, matching the
 * single status <select> on the frontend: draft | published | scheduled |
 * suspended (soft-deleted) — mirrors the Users module's combined-status
 * precedent rather than exposing two independent filters.
 *
 * Sort follows BACKEND-PHP §5.2; `page` / `per_page` stay on the request
 * (capped in the controller), matching Users.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class PostFilterData extends SoftDeleteFilterData
{
    /** @var list<string> */
    public const array SORTABLE = [
        'created_at',
        'post_title',
        'post_status',
        'published_at',
        'seo_score',
    ];

    public function __construct(
        ?string $search = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        public ?string $categoryUuid = null,
        public string $sortField = 'created_at',
        public int $sortOrder = -1,
    ) {
        parent::__construct($search, $status, $dateFrom, $dateTo);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::baseRules(),
            'status' => ['nullable', 'string', Rule::in(['draft', 'published', 'scheduled', 'suspended'])],
            'category_uuid' => ['nullable', 'uuid'],
            'sort_field' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'sort_order' => ['nullable', 'integer', 'in:1,-1'],
        ];
    }

    public function resolvedSortField(): string
    {
        return in_array($this->sortField, self::SORTABLE, true)
            ? $this->sortField
            : 'created_at';
    }

    public function resolvedSortDirection(): string
    {
        return $this->sortOrder === 1 ? 'asc' : 'desc';
    }
}
