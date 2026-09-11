<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\VideoEditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;

/**
 * @internal Application code goes through the module's repository port, not this model.
 *
 * Hard-deleted by design (spec 001-video-edit Q2/Q5): no SoftDeletes, and no
 * LogsActivity — submissions, retries and deletions are audited explicitly via
 * AuditPort so that only audit entries survive a deletion (plan D-1, D-2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $previous_edit_id
 * @property VideoEditMode $mode
 * @property VideoEditStatus $status
 * @property array<string, mixed> $parameters
 * @property array<string, mixed>|null $effective_settings
 * @property int $progress_percent
 * @property ProcessingStage|null $current_stage
 * @property int $attempts
 * @property string|null $failure_code
 * @property string|null $failure_message
 * @property array<string, mixed>|null $failure_details
 * @property int|null $original_duration_ms
 * @property int|null $final_duration_ms
 * @property int|null $removed_duration_ms
 * @property int $applied_cut_count
 * @property int $rejected_decision_count
 * @property list<string>|null $warnings
 * @property string|null $result_path
 * @property int|null $result_size_bytes
 * @property Carbon|null $sources_expire_at
 * @property Carbon|null $sources_purged_at
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read VideoEditEloquentModel|null $previousEdit
 * @property-read Collection<int, VideoEditSourceEloquentModel> $sources
 * @property-read int|null $sources_count
 * @property-read Collection<int, VideoEditCutDecisionEloquentModel> $cutDecisions
 * @property-read int|null $cut_decisions_count
 * @property-read Collection<int, VideoEditAppliedCutEloquentModel> $appliedCuts
 * @property-read int|null $applied_cuts_count
 *
 * @mixin \Eloquent
 */
#[Table('video_edits')]
#[Fillable([
    'uuid',
    'user_id',
    'previous_edit_id',
    'mode',
    'status',
    'parameters',
    'effective_settings',
    'progress_percent',
    'current_stage',
    'attempts',
    'failure_code',
    'failure_message',
    'failure_details',
    'original_duration_ms',
    'final_duration_ms',
    'removed_duration_ms',
    'applied_cut_count',
    'rejected_decision_count',
    'warnings',
    'result_path',
    'result_size_bytes',
    'sources_expire_at',
    'sources_purged_at',
    'queued_at',
    'started_at',
    'completed_at',
    'failed_at',
])]
final class VideoEditEloquentModel extends Model
{
    /** @use HasFactory<VideoEditFactory> */
    use HasFactory;

    /**
     * Internal keys and object paths never leave the backend (OWASP §12).
     *
     * @var list<string>
     */
    protected $hidden = ['id', 'user_id', 'previous_edit_id', 'result_path'];

    /**
     * Mirrors the column defaults so a freshly created model is complete
     * without a round-trip `refresh()`.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'progress_percent' => 0,
        'attempts' => 0,
        'applied_cut_count' => 0,
        'rejected_decision_count' => 0,
    ];

    protected static function booted(): void
    {
        self::creating(function (VideoEditEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<VideoEditEloquentModel, $this>
     */
    public function previousEdit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_edit_id');
    }

    /**
     * Unordered on purpose: an ORDER BY here would leak into `withCount()`
     * subqueries, which PostgreSQL rejects. Ordering lives in the repository.
     *
     * @return HasMany<VideoEditSourceEloquentModel, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(VideoEditSourceEloquentModel::class, 'video_edit_id');
    }

    /**
     * @return HasMany<VideoEditCutDecisionEloquentModel, $this>
     */
    public function cutDecisions(): HasMany
    {
        return $this->hasMany(VideoEditCutDecisionEloquentModel::class, 'video_edit_id');
    }

    /**
     * @return HasMany<VideoEditAppliedCutEloquentModel, $this>
     */
    public function appliedCuts(): HasMany
    {
        return $this->hasMany(VideoEditAppliedCutEloquentModel::class, 'video_edit_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'previous_edit_id' => 'integer',
            'mode' => VideoEditMode::class,
            'status' => VideoEditStatus::class,
            'parameters' => 'array',
            'effective_settings' => 'array',
            'progress_percent' => 'integer',
            'current_stage' => ProcessingStage::class,
            'attempts' => 'integer',
            'failure_details' => 'array',
            'original_duration_ms' => 'integer',
            'final_duration_ms' => 'integer',
            'removed_duration_ms' => 'integer',
            'applied_cut_count' => 'integer',
            'rejected_decision_count' => 'integer',
            'warnings' => 'array',
            'result_size_bytes' => 'integer',
            'sources_expire_at' => 'datetime',
            'sources_purged_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): VideoEditFactory
    {
        return VideoEditFactory::new();
    }
}
