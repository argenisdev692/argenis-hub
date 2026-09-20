<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int $source_id
 * @property string $slug
 * @property string|null $company_name
 * @property string $status
 * @property string|null $ats_kind
 * @property string|null $discovered_via
 * @property CarbonImmutable|null $verified_at
 * @property int $job_count_last_seen
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioSourceEloquentModel|null $source
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereAtsKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereCompanyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereDiscoveredVia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereJobCountLastSeen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceCompanyEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_source_companies')]
#[Fillable(['uuid', 'user_id', 'source_id', 'slug', 'company_name', 'status', 'ats_kind', 'discovered_via', 'verified_at', 'job_count_last_seen', 'last_seen_at'])]
final class StudioSourceCompanyEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSourceCompanyEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioSourceEloquentModel, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(StudioSourceEloquentModel::class, 'source_id');
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
            'source_id' => 'integer',
            'verified_at' => 'datetime',
            'job_count_last_seen' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
