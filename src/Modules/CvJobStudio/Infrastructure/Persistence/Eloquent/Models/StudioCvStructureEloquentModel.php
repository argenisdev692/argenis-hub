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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The addressable form of a CV (FR-4). `raw_text` on `cvs` stays the immutable
 * source of truth; these rows are derived and re-derivable. Unusable until
 * the candidate confirms the parse (`confirmed_at`, RK-6).
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $cv_id
 * @property string|null $source_text_hash
 * @property string|null $parser_version
 * @property CarbonImmutable|null $parsed_at
 * @property CarbonImmutable|null $confirmed_at
 * @property array<array-key, mixed>|null $profile_facts
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, StudioCvEntryEloquentModel> $entries
 * @property-read int|null $entries_count
 * @property-read Collection<int, StudioCvSkillEloquentModel> $skills
 * @property-read int|null $skills_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioCvStructureEloquentModel newModelQuery()
 * @method static Builder<static>|StudioCvStructureEloquentModel newQuery()
 * @method static Builder<static>|StudioCvStructureEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioCvStructureEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioCvStructureEloquentModel query()
 * @method static Builder<static>|StudioCvStructureEloquentModel whereConfirmedAt($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereCvId($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereId($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereParsedAt($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereParserVersion($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereProfileFacts($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereSourceTextHash($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioCvStructureEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioCvStructureEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_cv_structures')]
#[Fillable(['uuid', 'user_id', 'cv_id', 'source_text_hash', 'parser_version', 'parsed_at', 'confirmed_at', 'profile_facts'])]
final class StudioCvStructureEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvStructureEloquentModel $model): void {
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

    /** @return HasMany<StudioCvEntryEloquentModel, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StudioCvEntryEloquentModel::class, 'structure_id');
    }

    /** @return HasMany<StudioCvSkillEloquentModel, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(StudioCvSkillEloquentModel::class, 'structure_id');
    }

    /**
     * @param  Builder<StudioCvStructureEloquentModel>  $query
     * @return Builder<StudioCvStructureEloquentModel>
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
            'cv_id' => 'integer',
            'parsed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'profile_facts' => 'array',
        ];
    }

    /** Confirmation gates every rewrite and tailoring (RK-6); `profile_facts` is PII and never logged. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['parser_version', 'confirmed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.cv_structures');
    }
}
