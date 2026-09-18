<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\SearchPurpose;
use Modules\LeadScout\Domain\Enums\SearchStatus;

/**
 * Cached search query: results kept 30 days, identical combinations never
 * re-run inside the window (spec FR-12).
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_search_queries')]
#[Fillable([
    'query_hash',
    'query_text',
    'search_depth',
    'purpose',
    'discovery_wave',
    'family',
    'country',
    'results',
    'new_companies_count',
    'status',
    'cost_micros',
])]
final class ScoutSearchQueryEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => SearchPurpose::class,
            'results' => 'array',
            'new_companies_count' => 'integer',
            'status' => SearchStatus::class,
            'cost_micros' => 'integer',
        ];
    }
}
