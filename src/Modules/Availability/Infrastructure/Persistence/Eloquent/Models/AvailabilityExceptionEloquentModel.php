<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\AvailabilityExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Casts\TimeOfDay;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property Carbon $date
 * @property bool $is_available
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 * @property ExceptionSource $source
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
#[Table('availability_exceptions')]
#[Fillable(['uuid', 'date', 'is_available', 'start_time', 'end_time', 'reason', 'source'])]
final class AvailabilityExceptionEloquentModel extends Model
{
    /** @use HasFactory<AvailabilityExceptionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (AvailabilityExceptionEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1). `suspended` status is applied via
     * `onlyTrashed()` at the repository; the date window narrows to a period.
     *
     * `search` matches `reason`, the one free-text column on this table. Holiday
     * rows carry the holiday's own name in it, so a single box finds both a
     * manual note and a materialised national holiday.
     *
     * @param  Builder<AvailabilityExceptionEloquentModel>  $query
     * @return Builder<AvailabilityExceptionEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, AvailabilityExceptionFilterData $filters): Builder
    {
        return $query
            ->when(
                $filters->search !== null && $filters->search !== '',
                fn ($q) => $q->where('reason', 'like', '%'.$filters->search.'%'),
            )
            ->when($filters->availability === 'open', fn ($q) => $q->where('is_available', true))
            ->when($filters->availability === 'closed', fn ($q) => $q->where('is_available', false))
            ->when($filters->dateFrom !== null, fn ($q) => $q->whereDate('date', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn ($q) => $q->whereDate('date', '<=', $filters->dateTo));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // `date:Y-m-d` (not bare `date`) so the column stores a plain date on
            // every engine. With the default `Y-m-d H:i:s` serialisation SQLite
            // keeps the midnight suffix, and `Rule::unique(...)` then compares
            // `2026-11-02` against `2026-11-02 00:00:00`, misses, and lets the
            // duplicate through to the partial unique index as a 500 instead of
            // a 422.
            'date' => 'date:Y-m-d',
            'is_available' => 'boolean',
            'start_time' => TimeOfDay::class,
            'end_time' => TimeOfDay::class,
            'source' => ExceptionSource::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'is_available', 'start_time', 'end_time', 'reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('availability.exception');
    }

    protected static function newFactory(): AvailabilityExceptionFactory
    {
        return AvailabilityExceptionFactory::new();
    }
}
