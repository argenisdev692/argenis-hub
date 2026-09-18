<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;

/**
 * Decision rule fixed BEFORE the first measured contact (spec US-6 CA-3,
 * FR-19). Locked rules are immutable — only a new version for a new period.
 *
 * @property int $id
 * @property string $uuid
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_decision_rules')]
#[Fillable([
    'uuid',
    'sample_size',
    'window_days',
    'thresholds',
    'locked_at',
    'result',
    'period_starts_at',
    'period_ends_at',
])]
final class ScoutDecisionRuleEloquentModel extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sample_size' => 'integer',
            'window_days' => 'integer',
            'thresholds' => 'array',
            'locked_at' => 'datetime',
            'result' => DecisionOutcome::class,
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
        ];
    }
}
