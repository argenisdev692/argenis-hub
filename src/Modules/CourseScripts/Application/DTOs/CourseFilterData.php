<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Course list and listing-export filters (US-10, US-16 · BACKEND-PHP §5.2).
 *
 * **Documented deviation from the canonical shape.** §5.2 folds soft-delete
 * state into `status`, but here `status` is the generation lifecycle
 * ({@see CourseStatus}) the author filters by. Soft-delete state is therefore
 * its own axis, `trashed`, named after Eloquent's own vocabulary.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class CourseFilterData extends Data
{
    /**
     * `sort_field` reaches ORDER BY, so it is an allow-list (OWASP §3).
     *
     * @var list<string>
     */
    public const array SORTABLE_FIELDS = ['created_at', 'updated_at', 'title', 'status'];

    /**
     * `without` = live courses only (default), `only` = the recovery bin,
     * `with` = both.
     *
     * @var list<string>
     */
    public const array TRASHED_VALUES = ['without', 'only', 'with'];

    public function __construct(
        public ?string $search = null,
        public ?CourseStatus $status = null,
        public string $trashed = 'without',
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
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_column(CourseStatus::cases(), 'value'))],
            // `without` (default) lists live courses, `only` the soft-deleted
            // ones still recoverable, `with` both.
            'trashed' => ['string', Rule::in(self::TRASHED_VALUES)],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_field' => ['string', Rule::in(self::SORTABLE_FIELDS)],
            'sort_order' => ['integer', Rule::in([1, -1])],
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
        ];
    }

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
