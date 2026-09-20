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
 * @property int $run_id
 * @property int $jds_analyzed
 * @property string|null $sample_note
 * @property array<array-key, mixed>|null $requirements
 * @property array<array-key, mixed>|null $reach_table
 * @property array<array-key, mixed>|null $recommendations
 * @property array<array-key, mixed>|null $outcome_correlation
 * @property int $rules_version
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioRunEloquentModel|null $run
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereJdsAnalyzed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereOutcomeCorrelation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereReachTable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereRecommendations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereRequirements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereRulesVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereSampleNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioInsightReportEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_insight_reports')]
#[Fillable(['uuid', 'user_id', 'run_id', 'jds_analyzed', 'sample_note', 'requirements', 'reach_table', 'recommendations', 'outcome_correlation', 'rules_version'])]
final class StudioInsightReportEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioInsightReportEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioRunEloquentModel, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(StudioRunEloquentModel::class, 'run_id');
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
            'run_id' => 'integer',
            'jds_analyzed' => 'integer',
            'requirements' => 'array',
            'reach_table' => 'array',
            'recommendations' => 'array',
            'outcome_correlation' => 'array',
            'rules_version' => 'integer',
        ];
    }
}
