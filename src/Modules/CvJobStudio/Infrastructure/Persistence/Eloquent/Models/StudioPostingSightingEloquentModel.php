<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One observation of a posting on one source — the basis of re-listing and
 * expiry detection (FR-41). Pruned after 180 days; sightings are signal, not
 * history worth keeping forever.
 *
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
 * @property CarbonImmutable|null $observed_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioPostingEloquentModel|null $posting
 * @property-read StudioSourceEloquentModel|null $source
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioPostingSightingEloquentModel newModelQuery()
 * @method static Builder<static>|StudioPostingSightingEloquentModel newQuery()
 * @method static Builder<static>|StudioPostingSightingEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioPostingSightingEloquentModel query()
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereId($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereObservedAt($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel wherePostingId($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereSourceId($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioPostingSightingEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioPostingSightingEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_posting_sightings')]
#[Fillable(['uuid', 'user_id', 'posting_id', 'source_id', 'observed_at'])]
final class StudioPostingSightingEloquentModel extends Model
{
    use MassPrunable, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioPostingSightingEloquentModel $model): void {
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

    /** @return Builder<StudioPostingSightingEloquentModel> */
    public function prunable(): Builder
    {
        return self::where('observed_at', '<=', now()->subDays(180));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'posting_id' => 'integer',
            'source_id' => 'integer',
            'observed_at' => 'datetime',
        ];
    }
}
