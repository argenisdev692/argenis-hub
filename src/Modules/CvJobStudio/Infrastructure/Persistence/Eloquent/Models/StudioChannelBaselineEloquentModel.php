<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Opportunity policy values with evidence grade + source (FR-51). Own outcome
 * data replaces a policy value only past the evidence gate — this row is the
 * audit trail of that promotion, hence the activity log (T-091).
 *
 * @property int $id
 * @property string $uuid
 * @property string $bucket_kind
 * @property string $bucket
 * @property float $policy_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_channel_baselines')]
#[Fillable(['uuid', 'user_id', 'bucket_kind', 'bucket', 'policy_value', 'grade', 'source', 'positives', 'trials', 'applied_from', 'gate_passed_at'])]
final class StudioChannelBaselineEloquentModel extends Model
{
    use LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioChannelBaselineEloquentModel $model): void {
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'policy_value' => 'decimal:4',
            'positives' => 'integer',
            'trials' => 'integer',
            'applied_from' => 'datetime',
            'gate_passed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['bucket_kind', 'bucket', 'policy_value', 'grade', 'positives', 'trials'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.baselines');
    }
}
