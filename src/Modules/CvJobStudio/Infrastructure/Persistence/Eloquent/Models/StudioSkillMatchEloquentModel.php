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
 * @property int $score_id
 * @property int $requirement_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudioScoreEloquentModel $score
 * @property-read StudioRequirementEloquentModel $requirement
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_skill_matches')]
#[Fillable([
    'uuid',
    'user_id',
    'score_id',
    'requirement_id',
    'credit_base',
    'context_factor',
    'position_factor',
    'credit_final',
    'evidence_text',
])]
final class StudioSkillMatchEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSkillMatchEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioScoreEloquentModel, $this> */
    public function score(): BelongsTo
    {
        return $this->belongsTo(StudioScoreEloquentModel::class, 'score_id');
    }

    /** @return BelongsTo<StudioRequirementEloquentModel, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(StudioRequirementEloquentModel::class, 'requirement_id');
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
            'score_id' => 'integer',
            'requirement_id' => 'integer',
            'credit_base' => 'decimal:2',
            'context_factor' => 'decimal:2',
            'position_factor' => 'decimal:2',
            'credit_final' => 'decimal:4',
        ];
    }
}
