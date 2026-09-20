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
 * @property string $country_code
 * @property string $host
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioSourceEloquentModel|null $source
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioSourceLocaleEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_source_locales')]
#[Fillable(['uuid', 'user_id', 'source_id', 'country_code', 'host'])]
final class StudioSourceLocaleEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSourceLocaleEloquentModel $model): void {
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
        return ['user_id' => 'integer', 'source_id' => 'integer'];
    }
}
