<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Domain\ValueObjects\DateException;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see AvailabilityExceptionRepositoryPort}. Bulk
 * soft-delete/restore are inherited from the Shared {@see BulkSoftDeletesByUuid}
 * trait (DRY).
 */
final readonly class EloquentAvailabilityExceptionRepository implements AvailabilityExceptionRepositoryPort
{
    use BulkSoftDeletesByUuid;

    /**
     * @return class-string<AvailabilityExceptionEloquentModel>
     */
    protected function model(): string
    {
        return AvailabilityExceptionEloquentModel::class;
    }

    public function paginate(AvailabilityExceptionFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return AvailabilityExceptionEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->orderBy('date')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?AvailabilityExceptionEloquentModel
    {
        return AvailabilityExceptionEloquentModel::withTrashed()
            ->where('uuid', $uuid)
            ->first();
    }

    public function findActiveByDate(string $date): ?DateException
    {
        $exception = AvailabilityExceptionEloquentModel::query()
            ->whereDate('date', $date)
            ->first();

        return $exception === null ? null : self::toVo($exception);
    }

    public function activeBetween(string $from, string $to): array
    {
        return AvailabilityExceptionEloquentModel::query()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->mapWithKeys(static fn (AvailabilityExceptionEloquentModel $exception): array => [
                $exception->date->format('Y-m-d') => self::toVo($exception),
            ])
            ->all();
    }

    private static function toVo(AvailabilityExceptionEloquentModel $exception): DateException
    {
        return new DateException(
            $exception->date->format('Y-m-d'),
            $exception->is_available,
            $exception->start_time !== null ? substr((string) $exception->start_time, 0, 5) : null,
            $exception->end_time !== null ? substr((string) $exception->end_time, 0, 5) : null,
            $exception->reason,
        );
    }

    public function create(array $attributes): AvailabilityExceptionEloquentModel
    {
        // The UUID is minted here rather than left to the model's `creating`
        // hook: seeders run under `WithoutModelEvents`, so the hook would never
        // fire for them. Doing it in the adapter keeps `Str` out of the Domain
        // service that drives the holiday sync.
        return AvailabilityExceptionEloquentModel::query()->create([
            'uuid' => (string) Str::uuid7(),
            ...$attributes,
        ]);
    }

    public function purgeHolidaysFrom(string $date): int
    {
        return AvailabilityExceptionEloquentModel::withTrashed()
            ->where('source', ExceptionSource::Holiday->value)
            ->whereDate('date', '>=', $date)
            ->forceDelete();
    }

    public function update(AvailabilityExceptionEloquentModel $exception, array $attributes): AvailabilityExceptionEloquentModel
    {
        $exception->update($attributes);

        return $exception->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        return (bool) AvailabilityExceptionEloquentModel::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): bool
    {
        return (bool) AvailabilityExceptionEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
