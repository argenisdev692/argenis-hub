<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Domain\ValueObjects\TimeSlot;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

interface AvailabilityRuleRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, AvailabilityRuleEloquentModel>
     */
    public function paginate(AvailabilityRuleFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?AvailabilityRuleEloquentModel;

    /**
     * Available (open) slots for a weekday, ordered by start time, as domain
     * TimeSlots. Used by the AvailabilityResolver to build a day's open windows.
     *
     * @return list<TimeSlot>
     */
    public function availableSlotsForDay(int $dayOfWeek): array;

    /**
     * All available (open) slots grouped by weekday (0–6), ordered by start
     * time, as domain TimeSlots. Lets the AvailabilityResolver build a whole
     * date range without a per-day query.
     *
     * @return array<int, list<TimeSlot>>
     */
    public function availableSlotsGroupedByDay(): array;

    /**
     * Whether an ACTIVE, available slot on $dayOfWeek already overlaps the
     * `H:i` window [$startTime, $endTime). Two intervals overlap when
     * `existing.start < new.end` AND `existing.end > new.start`. $exceptUuid
     * excludes the row being edited so an update never conflicts with itself.
     *
     * Unavailable rows never conflict — only open windows must stay disjoint so
     * the resolver cannot emit an ambiguous day.
     */
    public function hasOverlappingAvailableSlot(
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?string $exceptUuid = null,
    ): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AvailabilityRuleEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AvailabilityRuleEloquentModel $rule, array $attributes): AvailabilityRuleEloquentModel;

    public function softDelete(string $uuid): bool;

    public function restore(string $uuid): bool;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteByUuid(array $uuids): int;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreByUuid(array $uuids): int;
}
