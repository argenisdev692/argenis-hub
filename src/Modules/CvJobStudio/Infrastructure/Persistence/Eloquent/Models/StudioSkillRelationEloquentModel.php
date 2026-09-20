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
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $from_skill
 * @property string $to_skill
 * @property string $kind
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @internal
 *
 * @property string $origin
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereFromSkill($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereToSkill($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSkillRelationEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_skill_relations')]
#[Fillable([
    'uuid',
    'user_id',
    'from_skill',
    'to_skill',
    'kind',
    'origin',
    'status',
])]
final class StudioSkillRelationEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSkillRelationEloquentModel $model): void {
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
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['from_skill', 'to_skill', 'kind', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.relations');
    }
}
