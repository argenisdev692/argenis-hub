<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\LeadScout\Domain\Enums\FetchMethod;
use Modules\LeadScout\Domain\Enums\FetchStatus;

/**
 * Every fetch attempt: method, tries, state (incl. `blocked`), duration,
 * estimated cost and failure reason (spec US-8 CA-3, FR-11).
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_fetch_attempts')]
#[Fillable([
    'company_id',
    'source_id',
    'url',
    'method',
    'status',
    'attempts',
    'duration_ms',
    'cost_micros',
    'error',
])]
final class ScoutFetchAttemptEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => FetchMethod::class,
            'status' => FetchStatus::class,
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'cost_micros' => 'integer',
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
     * @return BelongsTo<ScoutSourceEloquentModel, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ScoutSourceEloquentModel::class, 'source_id');
    }
}
