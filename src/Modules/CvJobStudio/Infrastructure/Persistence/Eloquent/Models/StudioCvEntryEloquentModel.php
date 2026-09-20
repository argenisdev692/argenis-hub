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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $structure_id
 * @property string $kind
 * @property int $ordinal
 * @property string|null $organization
 * @property string|null $role_title
 * @property string|null $location
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $ended_on
 * @property bool $is_current
 * @property bool $is_protected
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StudioCvBulletEloquentModel> $bullets
 * @property-read int|null $bullets_count
 * @property-read StudioCvStructureEloquentModel|null $structure
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereEndedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereIsCurrent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereIsProtected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereOrganization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereRoleTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereStartedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvEntryEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_cv_entries')]
#[Fillable(['uuid', 'user_id', 'structure_id', 'kind', 'ordinal', 'organization', 'role_title', 'location', 'started_on', 'ended_on', 'is_current', 'is_protected'])]
final class StudioCvEntryEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvEntryEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvStructureEloquentModel, $this> */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(StudioCvStructureEloquentModel::class, 'structure_id');
    }

    /** @return HasMany<StudioCvBulletEloquentModel, $this> */
    public function bullets(): HasMany
    {
        return $this->hasMany(StudioCvBulletEloquentModel::class, 'entry_id');
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
            'structure_id' => 'integer',
            'ordinal' => 'integer',
            'started_on' => 'date',
            'ended_on' => 'date',
            'is_current' => 'boolean',
            'is_protected' => 'boolean',
        ];
    }
}
