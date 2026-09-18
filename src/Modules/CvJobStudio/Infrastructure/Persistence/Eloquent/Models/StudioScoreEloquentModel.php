<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
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
 * @property int $posting_id
 * @property float $total_score
 * @property float $raw_score
 * @property string|null $band
 * @property int $rules_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudioPostingEloquentModel $posting
 * @property-read Collection<int, StudioSkillMatchEloquentModel> $skillMatches
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_scores')]
#[Fillable([
    'uuid',
    'user_id',
    'posting_id',
    'h',
    's',
    'd',
    'h_components',
    's_components',
    'd_components',
    'raw_score',
    'total_score',
    'band',
    'caps_applied',
    'cap_reason',
    'embedding_model',
    'embedding_dims',
    'o_components',
    'opportunity_static',
    'apply_priority',
    'priority_computed_at',
    'rules_version',
    'computed_at',
])]
final class StudioScoreEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioScoreEloquentModel $model): void {
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

    /** @return HasMany<StudioSkillMatchEloquentModel, $this> */
    public function skillMatches(): HasMany
    {
        return $this->hasMany(StudioSkillMatchEloquentModel::class, 'score_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'posting_id' => 'integer',
            'h' => 'decimal:2',
            's' => 'decimal:2',
            'd' => 'decimal:2',
            'h_components' => 'array',
            's_components' => 'array',
            'd_components' => 'array',
            'raw_score' => 'decimal:2',
            'total_score' => 'decimal:2',
            'caps_applied' => 'array',
            'embedding_dims' => 'integer',
            'o_components' => 'array',
            'opportunity_static' => 'decimal:4',
            'apply_priority' => 'decimal:4',
            'priority_computed_at' => 'datetime',
            'rules_version' => 'integer',
            'computed_at' => 'datetime',
        ];
    }
}
