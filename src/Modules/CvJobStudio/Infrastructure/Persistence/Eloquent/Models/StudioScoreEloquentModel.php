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
 * @internal
 *
 * @property int $user_id
 * @property numeric $h
 * @property numeric $s
 * @property numeric $d
 * @property array<array-key, mixed>|null $h_components
 * @property array<array-key, mixed>|null $s_components
 * @property array<array-key, mixed>|null $d_components
 * @property array<array-key, mixed>|null $caps_applied
 * @property string|null $cap_reason
 * @property string|null $embedding_model
 * @property int|null $embedding_dims
 * @property array<array-key, mixed>|null $o_components
 * @property numeric|null $opportunity_static
 * @property numeric|null $apply_priority
 * @property CarbonImmutable|null $priority_computed_at
 * @property CarbonImmutable|null $computed_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read int|null $skill_matches_count
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereApplyPriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereBand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereCapReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereCapsApplied($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereComputedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereD($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereDComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereEmbeddingDims($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereEmbeddingModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereH($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereHComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereOComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereOpportunityStatic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel wherePostingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel wherePriorityComputedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereRawScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereRulesVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereS($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereSComponents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereTotalScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioScoreEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
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
