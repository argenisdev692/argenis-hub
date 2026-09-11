<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\DTOs;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\ActivityLog\Application\Queries\ListActivityLogsHandler;
use Shared\Application\DTOs\Concerns\DateRangeFilterRules;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared list/export filter for the (read-only) activity-log trail — consumed by
 * both {@see ListActivityLogsHandler} and the export controller through the
 * single {@see self::applyTo()} (DRY).
 *
 * The trail is immutable: there is no status / soft-delete axis, only a query
 * window. `date_from` / `date_to` filter on `created_at` with inclusive
 * boundaries (`startOfDay()` / `endOfDay()`), and either bound may stand alone
 * (BACKEND-PHP §5.2).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class ActivityLogFilterData extends Data
{
    use DateRangeFilterRules;

    public function __construct(
        public ?string $search = null,
        public ?string $event = null,
        public ?string $logName = null,
        public ?string $causerId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public string $sortDirection = 'desc',
        public int $perPage = 15,
    ) {}

    /**
     * The comments below are published as these query parameters' descriptions
     * in `api.json` — write them for an API consumer, not for the next
     * maintainer, whose notes belong in this docblock.
     *
     * `search` matches the entry description, event, log name or subject type;
     * the date window narrows on when the entry was recorded. Note that
     * `search` is a partial match while `event` and `log_name` below are exact,
     * so the two are complementary rather than redundant.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            ...self::dateRangeRules(),
            // Exact match on the action recorded — `created`, `updated`,
            // `deleted`. Use `search` for a partial match.
            'event' => ['nullable', 'string', 'max:255'],
            // Exact match on the log channel the entry was written to.
            'log_name' => ['nullable', 'string', 'max:255'],
            // Restrict to the entries caused by one actor, by their id.
            'causer_id' => ['nullable', 'string', 'max:255'],
            // Order by recency: `desc` newest first (the default), `asc` oldest.
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            // Rows per page, clamped to 1–100.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * `asc` | `desc`, defaulting to newest-first — whitelisted here so callers
     * never pass an arbitrary direction into `orderBy()`.
     */
    public function normalizedSortDirection(): string
    {
        return $this->sortDirection === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Page size clamped to [1, 100] to bound resource consumption (OWASP API4).
     */
    public function normalizedPerPage(): int
    {
        return max(1, min(100, $this->perPage));
    }

    /**
     * Apply every active filter to an Activity query. Single source of truth so
     * the list and the export never drift.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $from = $this->filled($this->dateFrom) ? CarbonImmutable::parse($this->dateFrom)->startOfDay() : null;
        $to = $this->filled($this->dateTo) ? CarbonImmutable::parse($this->dateTo)->endOfDay() : null;

        return $query
            ->when($this->filled($this->search), function (Builder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $inner) use ($term): void {
                    $inner
                        ->where('description', 'like', $term)
                        ->orWhere('event', 'like', $term)
                        ->orWhere('log_name', 'like', $term)
                        ->orWhere('subject_type', 'like', $term);
                });
            })
            ->when($this->filled($this->event), fn (Builder $q) => $q->where('event', $this->event))
            ->when($this->filled($this->logName), fn (Builder $q) => $q->where('log_name', $this->logName))
            ->when($this->filled($this->causerId), fn (Builder $q) => $q->where('causer_id', $this->causerId))
            ->when($from !== null && $to !== null, fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($from !== null && $to === null, fn (Builder $q) => $q->where('created_at', '>=', $from))
            ->when($from === null && $to !== null, fn (Builder $q) => $q->where('created_at', '<=', $to));
    }

    private function filled(?string $value): bool
    {
        return $value !== null && $value !== '';
    }
}
