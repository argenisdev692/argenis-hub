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

/**
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_query_experiments')]
#[Fillable(['uuid', 'user_id', 'portal', 'template_id', 'locale', 'candidates', 'gate_pass_rate', 'unique_after_dedup', 'scored_gte_70', 'cost_micros', 'ran_at'])]
final class StudioQueryExperimentEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioQueryExperimentEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioQueryTemplateEloquentModel, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(StudioQueryTemplateEloquentModel::class, 'template_id');
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
            'template_id' => 'integer',
            'candidates' => 'integer',
            'gate_pass_rate' => 'decimal:4',
            'unique_after_dedup' => 'integer',
            'scored_gte_70' => 'integer',
            'cost_micros' => 'integer',
            'ran_at' => 'datetime',
        ];
    }
}
