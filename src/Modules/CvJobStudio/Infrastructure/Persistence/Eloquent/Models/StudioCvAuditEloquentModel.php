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
 * @property string|null $verdict
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
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
    public function scopeOwnedBy($query, int $userId)
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
