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
 * @property int $posting_id
 * @property string $ladder_step
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudioPostingEloquentModel $posting
 *
 * @internal
 *
 * @property int $user_id
 * @property string $completeness
 * @property string $text
 * @property int $char_count
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $fetched_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereCharCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereCompleteness($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereFetchedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereLadderStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel wherePostingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereResolutionNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioPostingTextEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_posting_texts')]
#[Fillable([
    'uuid',
    'user_id',
    'posting_id',
    'ladder_step',
    'completeness',
    'text',
    'char_count',
    'resolution_note',
    'fetched_at',
])]
final class StudioPostingTextEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioPostingTextEloquentModel $model): void {
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
            'char_count' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }
}
