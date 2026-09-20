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
 * @property string $period
 * @property string $category
 * @property int $limit_micros
 * @property int $spent_micros
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property CarbonImmutable|null $deleted_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereLimitMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereSpentMicros($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioBudgetEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_budgets')]
#[Fillable(['uuid', 'user_id', 'period', 'category', 'limit_micros', 'spent_micros'])]
final class StudioBudgetEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioBudgetEloquentModel $model): void {
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
            'limit_micros' => 'integer',
            'spent_micros' => 'integer',
        ];
    }
}
