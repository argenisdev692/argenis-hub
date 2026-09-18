<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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
 * @mixin \Eloquent
 *
 * @internal
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
    public function scopeOwnedBy($query, int $userId)
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
