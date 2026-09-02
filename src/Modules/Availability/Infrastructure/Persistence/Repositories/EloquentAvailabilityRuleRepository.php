<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Domain\ValueObjects\TimeSlot;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see AvailabilityRuleRepositoryPort}. Bulk
 * soft-delete/restore are inherited from the Shared {@see BulkSoftDeletesByUuid}
 * trait (DRY).
 */
final readonly class EloquentAvailabilityRuleRepository implements AvailabilityRuleRepositoryPort
{
    use BulkSoftDeletesByUuid;

    /**
     * @return class-string<AvailabilityRuleEloquentModel>
     */
    protected function model(): string
    {
        return AvailabilityRuleEloquentModel::class;
    }

    public function paginate(AvailabilityRuleFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return AvailabilityRuleEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?AvailabilityRuleEloquentModel
    {
        return AvailabilityRuleEloquentModel::withTrashed()
            ->where('uuid', $uuid)
            ->first();
    }

    public function availableSlotsForDay(int $dayOfWeek): array
    {
        return AvailabilityRuleEloquentModel::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time'])
            ->map(self::toSlot(...))
            ->all();
    }

    public function availableSlotsGroupedByDay(): array
    {
        return AvailabilityRuleEloquentModel::query()
            ->where('is_available', true)
            ->orderBy('start_time')
            ->get(['day_of_week', 'start_time', 'end_time'])
            ->groupBy('day_of_week')
            ->map(static fn ($rules): array => $rules->map(self::toSlot(...))->all())
            ->all();
    }

    private static function toSlot(AvailabilityRuleEloquentModel $rule): TimeSlot
    {
        return TimeSlot::fromRaw((string) $rule->start_time, (string) $rule->end_time);
    }

    public function hasOverlappingAvailableSlot(
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?string $exceptUuid = null,
    ): bool {
        return AvailabilityRuleEloquentModel::query()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->when($exceptUuid !== null, fn ($q) => $q->where('uuid', '!=', $exceptUuid))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    public function create(array $attributes): AvailabilityRuleEloquentModel
    {
        return AvailabilityRuleEloquentModel::query()->create($attributes);
    }

    public function update(AvailabilityRuleEloquentModel $rule, array $attributes): AvailabilityRuleEloquentModel
    {
        $rule->update($attributes);

        return $rule->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        return (bool) AvailabilityRuleEloquentModel::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): bool
    {
        return (bool) AvailabilityRuleEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
