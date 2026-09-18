<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Candidate-side status and employer-side outcome are separate fields
 * (FR-25). An aged silence may count as non-reply for analysis, but the
 * stored value is never overwritten with a guess (FR-29).
 *
 * @property int $id
 * @property string $uuid
 * @property string $status
 * @property string $outcome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_applications')]
#[Fillable(['uuid', 'user_id', 'posting_id', 'cv_version_id', 'export_id', 'status', 'applied_at', 'outcome', 'outcome_at', 'outcome_note'])]
final class StudioApplicationEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioApplicationEloquentModel $model): void {
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

    /**
     * @param  Builder<StudioApplicationEloquentModel>  $query
     * @return Builder<StudioApplicationEloquentModel>
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
            'posting_id' => 'integer',
            'cv_version_id' => 'integer',
            'export_id' => 'integer',
            'applied_at' => 'datetime',
            'outcome_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'outcome'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.applications');
    }
}
