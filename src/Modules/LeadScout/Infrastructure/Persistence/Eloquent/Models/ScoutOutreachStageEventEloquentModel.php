<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;

/**
 * Complete stage history, including round trips (spec US-6 CA-1).
 *
 * @property int $id
 * @property-read ScoutOutreachEloquentModel $outreach
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_outreach_stage_events')]
#[Fillable([
    'outreach_id',
    'from_stage',
    'to_stage',
    'reply_outcome',
    'operator_id',
    'created_at',
])]
final class ScoutOutreachStageEventEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_stage' => OutreachStage::class,
            'to_stage' => OutreachStage::class,
            'reply_outcome' => ReplyOutcome::class,
        ];
    }

    /**
     * @return BelongsTo<ScoutOutreachEloquentModel, $this>
     */
    public function outreach(): BelongsTo
    {
        return $this->belongsTo(ScoutOutreachEloquentModel::class, 'outreach_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
