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
 * @property int $posting_id
 * @property int $source_id
 * @property string|null $source_url
 * @property CarbonImmutable|null $seen_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioPostingEloquentModel|null $posting
 * @property-read StudioSourceEloquentModel|null $source
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel wherePostingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereSourceUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingSourceEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_posting_sources')]
#[Fillable(['uuid', 'user_id', 'posting_id', 'source_id', 'source_url', 'seen_at'])]
final class StudioPostingSourceEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioPostingSourceEloquentModel $model): void {
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
            'posting_id' => 'integer',
            'source_id' => 'integer',
            'seen_at' => 'datetime',
        ];
    }
}
