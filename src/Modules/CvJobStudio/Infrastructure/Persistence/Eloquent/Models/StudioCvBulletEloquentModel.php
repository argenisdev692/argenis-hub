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
 * @property int $structure_id
 * @property int $entry_id
 * @property int $ordinal
 * @property string $text
 * @property bool $has_metric
 * @property bool $xyz_complete
 * @property int $char_count
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioCvEntryEloquentModel|null $entry
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereCharCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereHasMetric($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel whereXyzComplete($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvBulletEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_cv_bullets')]
#[Fillable(['uuid', 'user_id', 'structure_id', 'entry_id', 'ordinal', 'text', 'has_metric', 'xyz_complete', 'char_count'])]
final class StudioCvBulletEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvBulletEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvEntryEloquentModel, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(StudioCvEntryEloquentModel::class, 'entry_id');
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
            'entry_id' => 'integer',
            'ordinal' => 'integer',
            'has_metric' => 'boolean',
            'xyz_complete' => 'boolean',
            'char_count' => 'integer',
        ];
    }
}
