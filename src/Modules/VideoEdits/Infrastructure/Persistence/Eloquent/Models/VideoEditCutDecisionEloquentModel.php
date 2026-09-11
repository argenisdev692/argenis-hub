<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;

/**
 * @internal Application code goes through the module's repository port, not this model.
 *
 * One decision proposed by a producer — applied or rejected — kept so the
 * summary and the future V3 report can explain every choice (EX-1, EX-8).
 * Append-only: no `updated_at`.
 *
 * @property int $id
 * @property int $video_edit_id
 * @property string $producer
 * @property CutReason $reason
 * @property DecisionOrigin $origin
 * @property int $start_ms
 * @property int $end_ms
 * @property string|null $confidence
 * @property array<string, mixed>|null $evidence
 * @property DecisionOutcome $outcome
 * @property string|null $rejection_reason
 * @property int|null $applied_cut_sequence
 * @property Carbon|null $created_at
 * @property-read VideoEditEloquentModel $videoEdit
 *
 * @mixin \Eloquent
 */
#[Table('video_edit_cut_decisions')]
#[Fillable([
    'video_edit_id',
    'producer',
    'reason',
    'origin',
    'start_ms',
    'end_ms',
    'confidence',
    'evidence',
    'outcome',
    'rejection_reason',
    'applied_cut_sequence',
])]
final class VideoEditCutDecisionEloquentModel extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'video_edit_id'];

    /**
     * @return BelongsTo<VideoEditEloquentModel, $this>
     */
    public function videoEdit(): BelongsTo
    {
        return $this->belongsTo(VideoEditEloquentModel::class, 'video_edit_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'video_edit_id' => 'integer',
            'reason' => CutReason::class,
            'origin' => DecisionOrigin::class,
            'start_ms' => 'integer',
            'end_ms' => 'integer',
            'confidence' => 'decimal:3',
            'evidence' => 'array',
            'outcome' => DecisionOutcome::class,
            'applied_cut_sequence' => 'integer',
        ];
    }
}
