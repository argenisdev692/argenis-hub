<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
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
 * Opportunity policy values with evidence grade + source (FR-51). Own outcome
 * data replaces a policy value only past the evidence gate — this row is the
 * audit trail of that promotion, hence the activity log (T-091).
 *
 * @property int $id
 * @property string $uuid
 * @property string $bucket_kind
 * @property string $bucket
 * @property float $policy_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property string $grade
 * @property string|null $source
 * @property int $positives
 * @property int $trials
 * @property CarbonImmutable|null $applied_from
 * @property CarbonImmutable|null $gate_passed_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereAppliedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereBucket($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereBucketKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereGatePassedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel wherePolicyValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel wherePositives($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereTrials($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioChannelBaselineEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_channel_baselines')]
#[Fillable(['uuid', 'user_id', 'bucket_kind', 'bucket', 'policy_value', 'grade', 'source', 'positives', 'trials', 'applied_from', 'gate_passed_at'])]
final class StudioChannelBaselineEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioChannelBaselineEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'policy_value' => 'decimal:4',
            'positives' => 'integer',
            'trials' => 'integer',
            'applied_from' => 'datetime',
            'gate_passed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['bucket_kind', 'bucket', 'policy_value', 'grade', 'positives', 'trials'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.baselines');
    }
}
