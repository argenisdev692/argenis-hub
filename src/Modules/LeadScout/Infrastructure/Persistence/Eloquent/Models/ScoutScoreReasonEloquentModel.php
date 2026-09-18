<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score reason: points, origin signal (real FK) and explanation
 * (spec US-4 CA-2: «¿por qué 87?»).
 *
 * @property int $id
 * @property-read ScoutScoreResultEloquentModel $scoreResult
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_score_reasons')]
#[Fillable([
    'score_result_id',
    'signal_id',
    'points',
    'explanation',
])]
final class ScoutScoreReasonEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    /**
     * @return BelongsTo<ScoutScoreResultEloquentModel, $this>
     */
    public function scoreResult(): BelongsTo
    {
        return $this->belongsTo(ScoutScoreResultEloquentModel::class, 'score_result_id');
    }

    /**
     * @return BelongsTo<ScoutSignalEloquentModel, $this>
     */
    public function signal(): BelongsTo
    {
        return $this->belongsTo(ScoutSignalEloquentModel::class, 'signal_id');
    }
}
