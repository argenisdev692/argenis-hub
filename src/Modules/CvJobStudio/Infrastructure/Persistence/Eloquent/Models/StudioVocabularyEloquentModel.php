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
 * @property int $profile_id
 * @property string $term
 * @property string $kind
 * @property string|null $language
 * @property numeric $weight
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $refreshed_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioProfileEloquentModel|null $profile
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereRefreshedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereTerm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel whereWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioVocabularyEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_vocabulary')]
#[Fillable(['uuid', 'user_id', 'profile_id', 'term', 'kind', 'language', 'weight', 'last_seen_at', 'refreshed_at'])]
final class StudioVocabularyEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioVocabularyEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioProfileEloquentModel, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(StudioProfileEloquentModel::class, 'profile_id');
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
            'profile_id' => 'integer',
            'weight' => 'decimal:4',
            'last_seen_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }
}
