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
 * Stored vectors with model + dims per row: a model change is a re-embed,
 * never silent corruption. Write-once per content hash. The `embedding` cast
 * is `array` on both drivers (native `vector` on pgsql, text stand-in on
 * SQLite — CHG-17).
 *
 * @property int $id
 * @property string $uuid
 * @property string $owner_type
 * @property int $owner_id
 * @property string $model
 * @property int $dims
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property string $content_hash
 * @property array<array-key, mixed> $embedding
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereContentHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereDims($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioEmbeddingEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_embeddings')]
#[Fillable(['uuid', 'user_id', 'owner_type', 'owner_id', 'model', 'dims', 'content_hash', 'embedding'])]
final class StudioEmbeddingEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioEmbeddingEloquentModel $model): void {
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
            'owner_id' => 'integer',
            'dims' => 'integer',
            'embedding' => 'array',
        ];
    }
}
