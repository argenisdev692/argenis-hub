<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LeadScout\Domain\Enums\OpportunityStatus;
use Modules\LeadScout\Domain\Enums\OpportunityType;

/**
 * Revenue attached to an outreach (spec US-6 CA-2: hours and euros billed).
 * Several opportunities can hang off one contact.
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutOutreachEloquentModel $outreach
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_opportunities')]
#[Fillable([
    'uuid',
    'outreach_id',
    'type',
    'hours_per_month',
    'hourly_rate_cents',
    'amount_cents',
    'currency',
    'status',
    'started_at',
    'ended_at',
])]
final class ScoutOpportunityEloquentModel extends Model
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
            'type' => OpportunityType::class,
            'hours_per_month' => 'integer',
            'hourly_rate_cents' => 'integer',
            'amount_cents' => 'integer',
            'status' => OpportunityStatus::class,
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ScoutOutreachEloquentModel, $this>
     */
    public function outreach(): BelongsTo
    {
        return $this->belongsTo(ScoutOutreachEloquentModel::class, 'outreach_id');
    }
}
