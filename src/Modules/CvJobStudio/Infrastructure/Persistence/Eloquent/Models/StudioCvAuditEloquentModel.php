<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $verdict
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $cv_id
 * @property int|null $structure_id
 * @property int|null $profile_id
 * @property array<array-key, mixed>|null $verdict_reasons
 * @property array<array-key, mixed>|null $structural_checks
 * @property numeric|null $parse_recovery_ratio
 * @property array<array-key, mixed>|null $content_issues
 * @property string|null $target_job_title
 * @property array<array-key, mixed>|null $strengths
 * @property array<array-key, mixed>|null $improvements
 * @property array<array-key, mixed>|null $keyword_gaps
 * @property array<array-key, mixed>|null $xyz_gaps
 * @property int $rules_version
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StudioMetricAnswerEloquentModel> $metricAnswers
 * @property-read int|null $metric_answers_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioCvAuditEloquentModel newModelQuery()
 * @method static Builder<static>|StudioCvAuditEloquentModel newQuery()
 * @method static Builder<static>|StudioCvAuditEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioCvAuditEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioCvAuditEloquentModel query()
 * @method static Builder<static>|StudioCvAuditEloquentModel whereContentIssues($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereCvId($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereId($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereImprovements($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereKeywordGaps($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereParseRecoveryRatio($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereProfileId($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereRulesVersion($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereStrengths($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereStructuralChecks($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereStructureId($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereTargetJobTitle($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereVerdict($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereVerdictReasons($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel whereXyzGaps($value)
 * @method static Builder<static>|StudioCvAuditEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioCvAuditEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_cv_audits')]
#[Fillable([
    'uuid', 'user_id', 'cv_id', 'structure_id', 'profile_id', 'verdict', 'verdict_reasons',
    'structural_checks', 'parse_recovery_ratio', 'content_issues', 'target_job_title',
    'strengths', 'improvements', 'keyword_gaps', 'xyz_gaps', 'rules_version',
])]
final class StudioCvAuditEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvAuditEloquentModel $model): void {
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

    /** @return HasMany<StudioMetricAnswerEloquentModel, $this> */
    public function metricAnswers(): HasMany
    {
        return $this->hasMany(StudioMetricAnswerEloquentModel::class, 'audit_id');
    }

    /**
     * @param  Builder<StudioCvAuditEloquentModel>  $query
     * @return Builder<StudioCvAuditEloquentModel>
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cv_id' => 'integer',
            'structure_id' => 'integer',
            'profile_id' => 'integer',
            'verdict_reasons' => 'array',
            'structural_checks' => 'array',
            'parse_recovery_ratio' => 'decimal:4',
            'content_issues' => 'array',
            'strengths' => 'array',
            'improvements' => 'array',
            'keyword_gaps' => 'array',
            'xyz_gaps' => 'array',
            'rules_version' => 'integer',
        ];
    }
}
