<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Casts\TimeOfDay;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $day_of_week
 * @property string $start_time
 * @property string $end_time
 * @property bool $is_available
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
#[Table('availability_rules')]
#[Fillable(['uuid', 'day_of_week', 'start_time', 'end_time', 'is_available'])]
final class AvailabilityRuleEloquentModel extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (AvailabilityRuleEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1 — single scope, no duplicated
     * `when()` chains). `suspended` status is applied via `onlyTrashed()` at the
     * repository.
     *
     * @param  Builder<AvailabilityRuleEloquentModel>  $query
     * @return Builder<AvailabilityRuleEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, AvailabilityRuleFilterData $filters): Builder
    {
        return $query
            ->when($filters->dayOfWeek !== null, fn ($q) => $q->where('day_of_week', $filters->dayOfWeek))
            ->when($filters->availability === 'available', fn ($q) => $q->where('is_available', true))
            ->when($filters->availability === 'unavailable', fn ($q) => $q->where('is_available', false));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_available' => 'boolean',
            'start_time' => TimeOfDay::class,
            'end_time' => TimeOfDay::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['day_of_week', 'start_time', 'end_time', 'is_available'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('availability.rule');
    }

    protected static function newFactory(): AvailabilityRuleFactory
    {
        return AvailabilityRuleFactory::new();
    }
}
