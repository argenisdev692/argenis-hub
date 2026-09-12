<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * History and export filters (E1 · US-6 · BACKEND-PHP §5.2).
 *
 * One contract for both surfaces: `paginateOwned()` and the CSV/XLSX/PDF export
 * feed the same `scopeApplyFilters`, so a report can never disagree with the
 * table it was exported from. Drafts are never listed, so `draft` is not a
 * selectable status (D17).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class VideoEditFilterData extends Data
{
    /**
     * Sorting is an allow-list, not a passthrough: `sort_field` reaches
     * `ORDER BY`, where an arbitrary string would be an injection point
     * (OWASP §3). Anything unlisted falls back to `created_at`.
     *
     * @var list<string>
     */
    public const array SORTABLE_FIELDS = [
        'created_at', 'completed_at', 'status', 'mode', 'final_duration_ms', 'applied_cut_count',
    ];

    public function __construct(
        public ?string $search = null,
        public ?VideoEditStatus $status = null,
        public ?VideoEditMode $mode = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $sortField = 'created_at',
        public int $sortOrder = -1,
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $listedStatuses = array_values(array_map(
            static fn (VideoEditStatus $status): string => $status->value,
            array_filter(VideoEditStatus::cases(), static fn (VideoEditStatus $status): bool => $status->isListed()),
        ));

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($listedStatuses)],
            'mode' => ['nullable', 'string', Rule::in(array_column(VideoEditMode::cases(), 'value'))],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_field' => ['string', Rule::in(self::SORTABLE_FIELDS)],
            'sort_order' => ['integer', Rule::in([1, -1])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Validation rejects an unlisted field, but the DTO is also constructed
     * directly (scheduled reports, tests), so the allow-list is enforced here
     * too rather than trusted from one direction only.
     */
    #[\NoDiscard]
    public function safeSortField(): string
    {
        return in_array($this->sortField, self::SORTABLE_FIELDS, true) ? $this->sortField : 'created_at';
    }

    #[\NoDiscard]
    public function sortDirection(): string
    {
        return $this->sortOrder === 1 ? 'asc' : 'desc';
    }
}
