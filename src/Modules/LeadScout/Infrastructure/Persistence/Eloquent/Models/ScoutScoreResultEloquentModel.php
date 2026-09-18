<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutScoreResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\DiscardReason;
use Modules\LeadScout\Domain\Enums\Tier;

/**
 * Deterministic score snapshot (spec FR-8): subscores, Lead Score, evidence
 * confidence, tier and the rules/profile versions it was computed with.
 * Exactly one row per company is current (partial unique).
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_score_results')]
#[Fillable([
    'uuid',
    'company_id',
    'profile_id',
    'rules_version',
    'subscores',
    'lead_score',
    'confidence',
    'tier',
    'discard_reason',
    'is_current',
])]
final class ScoutScoreResultEloquentModel extends Model
{
    /** @use HasFactory<ScoutScoreResultFactory> */
    use HasFactory, HasUuids;

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
            'subscores' => 'array',
            'lead_score' => 'integer',
            'confidence' => 'integer',
            'tier' => Tier::class,
            'discard_reason' => DiscardReason::class,
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ScoutCompanyEloquentModel, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(ScoutCompanyEloquentModel::class, 'company_id');
    }

    /**
     * @return BelongsTo<ScoutProfileEloquentModel, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ScoutProfileEloquentModel::class, 'profile_id');
    }

    /**
     * @return HasMany<ScoutScoreReasonEloquentModel, $this>
     */
    public function reasons(): HasMany
    {
        return $this->hasMany(ScoutScoreReasonEloquentModel::class, 'score_result_id');
    }

    protected static function newFactory(): ScoutScoreResultFactory
    {
        return ScoutScoreResultFactory::new();
    }
}
