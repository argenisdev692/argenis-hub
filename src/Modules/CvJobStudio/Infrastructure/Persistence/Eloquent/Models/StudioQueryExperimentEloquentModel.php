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
 * @property string $portal
 * @property int|null $template_id
 * @property string|null $locale
 * @property int $candidates
 * @property numeric $gate_pass_rate
 * @property int $unique_after_dedup
 * @property int $scored_gte_70
 * @property int $cost_micros
 * @property CarbonImmutable|null $ran_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioQueryTemplateEloquentModel|null $template
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereCandidates($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereCostMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereGatePassRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel wherePortal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereRanAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereScoredGte70($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereTemplateId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereUniqueAfterDedup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioQueryExperimentEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_query_experiments')]
#[Fillable(['uuid', 'user_id', 'portal', 'template_id', 'locale', 'candidates', 'gate_pass_rate', 'unique_after_dedup', 'scored_gte_70', 'cost_micros', 'ran_at'])]
final class StudioQueryExperimentEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioQueryExperimentEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioQueryTemplateEloquentModel, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(StudioQueryTemplateEloquentModel::class, 'template_id');
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
            'template_id' => 'integer',
            'candidates' => 'integer',
            'gate_pass_rate' => 'decimal:4',
            'unique_after_dedup' => 'integer',
            'scored_gte_70' => 'integer',
            'cost_micros' => 'integer',
            'ran_at' => 'datetime',
        ];
    }
}
