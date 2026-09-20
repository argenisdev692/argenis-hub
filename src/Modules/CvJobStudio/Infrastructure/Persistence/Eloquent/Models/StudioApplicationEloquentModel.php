<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Candidate-side status and employer-side outcome are separate fields
 * (FR-25). An aged silence may count as non-reply for analysis, but the
 * stored value is never overwritten with a guess (FR-29).
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property string $outcome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $posting_id
 * @property int|null $cv_version_id
 * @property int|null $export_id
 * @property CarbonImmutable|null $applied_at
 * @property CarbonImmutable|null $outcome_at
 * @property string|null $outcome_note
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read StudioPostingEloquentModel|null $posting
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioApplicationEloquentModel newModelQuery()
 * @method static Builder<static>|StudioApplicationEloquentModel newQuery()
 * @method static Builder<static>|StudioApplicationEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioApplicationEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioApplicationEloquentModel query()
 * @method static Builder<static>|StudioApplicationEloquentModel whereAppliedAt($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereCvVersionId($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereExportId($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereId($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereOutcome($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereOutcomeAt($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereOutcomeNote($value)
 * @method static Builder<static>|StudioApplicationEloquentModel wherePostingId($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereStatus($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioApplicationEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioApplicationEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioApplicationEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_applications')]
#[Fillable(['uuid', 'user_id', 'posting_id', 'cv_version_id', 'export_id', 'status', 'applied_at', 'outcome', 'outcome_at', 'outcome_note'])]
final class StudioApplicationEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioApplicationEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioPostingEloquentModel, $this> */
    public function posting(): BelongsTo
    {
        return $this->belongsTo(StudioPostingEloquentModel::class, 'posting_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<StudioApplicationEloquentModel>  $query
     * @return Builder<StudioApplicationEloquentModel>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'posting_id' => 'integer',
            'cv_version_id' => 'integer',
            'export_id' => 'integer',
            'applied_at' => 'datetime',
            'outcome_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'outcome'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.applications');
    }
}
