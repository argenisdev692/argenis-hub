<?php

declare(strict_types=1);

namespace Modules\Availability\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Domain\ValueObjects\DateException;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;

interface AvailabilityExceptionRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, AvailabilityExceptionEloquentModel>
     */
    public function paginate(AvailabilityExceptionFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?AvailabilityExceptionEloquentModel;

    /**
     * The single active (non-deleted) exception for a date as a domain
     * projection, if any. Used by the AvailabilityResolver and the
     * HolidayMaterializer as the highest-precedence override.
     */
    public function findActiveByDate(string $date): ?DateException;

    /**
     * Active (non-deleted) exceptions between two dates (inclusive) as domain
     * projections, keyed by `Y-m-d`. Lets the AvailabilityResolver resolve a
     * whole range without a per-day query.
     *
     * @return array<string, DateException>
     */
    public function activeBetween(string $from, string $to): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AvailabilityExceptionEloquentModel;

    /**
     * Force-delete every holiday-sourced exception dated on/after $date. Manual
     * overrides are never removed. Used to rebuild holidays on a country change
     * or the yearly sync. Returns the number of rows purged.
     */
    public function purgeHolidaysFrom(string $date): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(AvailabilityExceptionEloquentModel $exception, array $attributes): AvailabilityExceptionEloquentModel;

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
