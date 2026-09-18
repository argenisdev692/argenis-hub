<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutSignalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\ExtractionMethod;
use Modules\LeadScout\Domain\Enums\SignalDimension;
use Modules\LeadScout\Domain\Enums\SignalNature;

/**
 * One evidence-backed signal: origin URL, literal excerpt, timestamp,
 * confidence, extraction method and fact|inference nature (spec FR-7).
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_signals')]
#[Fillable([
    'uuid',
    'company_id',
    'dimension',
    'signal_key',
    'value_text',
    'nature',
    'confidence',
    'evidence_url',
    'evidence_excerpt',
    'captured_at',
    'extraction_method',
    'ai_provider',
    'ai_model',
])]
final class ScoutSignalEloquentModel extends Model
{
    /** @use HasFactory<ScoutSignalFactory> */
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
            'dimension' => SignalDimension::class,
            'nature' => SignalNature::class,
            'confidence' => 'integer',
            'captured_at' => 'datetime',
            'extraction_method' => ExtractionMethod::class,
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
     * @return HasMany<ScoutScoreReasonEloquentModel, $this>
     */
    public function scoreReasons(): HasMany
    {
        return $this->hasMany(ScoutScoreReasonEloquentModel::class, 'signal_id');
    }

    protected static function newFactory(): ScoutSignalFactory
    {
        return ScoutSignalFactory::new();
    }
}
