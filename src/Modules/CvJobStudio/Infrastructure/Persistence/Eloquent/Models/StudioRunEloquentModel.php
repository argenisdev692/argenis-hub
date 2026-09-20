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
 * @property string $status
 * @property int $rules_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudioProfileEloquentModel $profile
 *
 * @internal
 *
 * @property int $user_id
 * @property int $profile_id
 * @property int|null $cv_id
 * @property int|null $structure_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property array<array-key, mixed>|null $queries
 * @property int $candidates_count
 * @property int $gate_passed_count
 * @property int $extracted_count
 * @property int $scored_count
 * @property int $new_matches_count
 * @property int $excluded_count
 * @property int $spend_micros
 * @property array<array-key, mixed>|null $refresh_flags
 * @property string|null $notes
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StudioInsightReportEloquentModel> $insightReports
 * @property-read int|null $insight_reports_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|StudioRunEloquentModel newModelQuery()
 * @method static Builder<static>|StudioRunEloquentModel newQuery()
 * @method static Builder<static>|StudioRunEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioRunEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioRunEloquentModel query()
 * @method static Builder<static>|StudioRunEloquentModel whereCandidatesCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioRunEloquentModel whereCvId($value)
 * @method static Builder<static>|StudioRunEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioRunEloquentModel whereExcludedCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereExtractedCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereFinishedAt($value)
 * @method static Builder<static>|StudioRunEloquentModel whereGatePassedCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereId($value)
 * @method static Builder<static>|StudioRunEloquentModel whereNewMatchesCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereNotes($value)
 * @method static Builder<static>|StudioRunEloquentModel whereProfileId($value)
 * @method static Builder<static>|StudioRunEloquentModel whereQueries($value)
 * @method static Builder<static>|StudioRunEloquentModel whereRefreshFlags($value)
 * @method static Builder<static>|StudioRunEloquentModel whereRulesVersion($value)
 * @method static Builder<static>|StudioRunEloquentModel whereScoredCount($value)
 * @method static Builder<static>|StudioRunEloquentModel whereSpendMicros($value)
 * @method static Builder<static>|StudioRunEloquentModel whereStartedAt($value)
 * @method static Builder<static>|StudioRunEloquentModel whereStatus($value)
 * @method static Builder<static>|StudioRunEloquentModel whereStructureId($value)
 * @method static Builder<static>|StudioRunEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioRunEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioRunEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioRunEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioRunEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_runs')]
#[Fillable([
    'uuid', 'user_id', 'profile_id', 'cv_id', 'structure_id', 'status', 'started_at', 'finished_at',
    'queries', 'candidates_count', 'gate_passed_count', 'extracted_count', 'scored_count',
    'new_matches_count', 'excluded_count', 'spend_micros', 'refresh_flags', 'notes', 'rules_version',
])]
final class StudioRunEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioRunEloquentModel $model): void {
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

    /** @return BelongsTo<StudioProfileEloquentModel, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(StudioProfileEloquentModel::class, 'profile_id');
    }

    /** @return HasMany<StudioInsightReportEloquentModel, $this> */
    public function insightReports(): HasMany
    {
        return $this->hasMany(StudioInsightReportEloquentModel::class, 'run_id');
    }

    /**
     * @param  Builder<StudioRunEloquentModel>  $query
     * @return Builder<StudioRunEloquentModel>
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
            'profile_id' => 'integer',
            'cv_id' => 'integer',
            'structure_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'queries' => 'array',
            'candidates_count' => 'integer',
            'gate_passed_count' => 'integer',
            'extracted_count' => 'integer',
            'scored_count' => 'integer',
            'new_matches_count' => 'integer',
            'excluded_count' => 'integer',
            'spend_micros' => 'integer',
            'refresh_flags' => 'array',
            'rules_version' => 'integer',
        ];
    }
}
