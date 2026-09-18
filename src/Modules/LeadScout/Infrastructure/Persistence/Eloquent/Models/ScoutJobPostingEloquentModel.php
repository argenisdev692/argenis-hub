<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutJobPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\LeadScout\Domain\Enums\ContractType;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\RemoteMode;

/**
 * Normalized job posting (spec US-2). A vacancy is a BUY signal on the
 * company, never republication material (spec FR-30).
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel|null $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_job_postings')]
#[Fillable([
    'uuid',
    'company_id',
    'fingerprint',
    'company_name',
    'title',
    'location',
    'country',
    'remote_mode',
    'contract_type',
    'language',
    'published_at',
    'expires_at',
    'status',
    'source_url',
    'company_url',
    'body_text',
])]
final class ScoutJobPostingEloquentModel extends Model
{
    /** @use HasFactory<ScoutJobPostingFactory> */
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
            'remote_mode' => RemoteMode::class,
            'contract_type' => ContractType::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => PostingStatus::class,
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
     * @return BelongsToMany<ScoutSourceEloquentModel, $this>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            ScoutSourceEloquentModel::class,
            'scout_job_posting_sources',
            'posting_id',
            'source_id',
        );
    }

    protected static function newFactory(): ScoutJobPostingFactory
    {
        return ScoutJobPostingFactory::new();
    }
}
