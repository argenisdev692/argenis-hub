<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;

/**
 * Ingest source registry (spec FR-2). A source is never activated without
 * reviewed terms (DB CHECK + 422, spec FR-13).
 *
 * @property int $id
 * @property string $uuid
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_sources')]
#[Fillable([
    'uuid',
    'name',
    'type',
    'country',
    'access_method',
    'frequency_minutes',
    'priority',
    'status',
    'last_run_at',
    'last_cursor',
    'terms_reviewed_at',
])]
final class ScoutSourceEloquentModel extends Model
{
    /** @use HasFactory<ScoutSourceFactory> */
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
            'type' => SourceType::class,
            'frequency_minutes' => 'integer',
            'priority' => 'integer',
            'status' => SourceStatus::class,
            'last_run_at' => 'datetime',
            'terms_reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<ScoutJobPostingEloquentModel, $this>
     */
    public function postings(): BelongsToMany
    {
        return $this->belongsToMany(
            ScoutJobPostingEloquentModel::class,
            'scout_job_posting_sources',
            'source_id',
            'posting_id',
        );
    }

    /**
     * @return HasMany<ScoutFetchAttemptEloquentModel, $this>
     */
    public function fetchAttempts(): HasMany
    {
        return $this->hasMany(ScoutFetchAttemptEloquentModel::class, 'source_id');
    }

    protected static function newFactory(): ScoutSourceFactory
    {
        return ScoutSourceFactory::new();
    }
}
