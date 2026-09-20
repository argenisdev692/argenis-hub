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
 * @property int $audit_id
 * @property int $cv_id
 * @property string $question
 * @property string|null $answer
 * @property CarbonImmutable|null $answered_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioCvAuditEloquentModel|null $audit
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereAuditId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereCvId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereQuestion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioMetricAnswerEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_metric_answers')]
#[Fillable(['uuid', 'user_id', 'audit_id', 'cv_id', 'question', 'answer', 'answered_at'])]
final class StudioMetricAnswerEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioMetricAnswerEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvAuditEloquentModel, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(StudioCvAuditEloquentModel::class, 'audit_id');
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
            'audit_id' => 'integer',
            'cv_id' => 'integer',
            'answered_at' => 'datetime',
        ];
    }
}
