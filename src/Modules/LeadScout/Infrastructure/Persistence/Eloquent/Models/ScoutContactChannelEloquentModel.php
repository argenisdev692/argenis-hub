<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutContactChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;

/**
 * Detected contact channel (spec US-12, FR-31). Forms are never filled,
 * sent or invoked (FR-32) — only their existence, fields and CAPTCHA flag.
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_contact_channels')]
#[Fillable([
    'uuid',
    'company_id',
    'channel_type',
    'url',
    'generic_email',
    'form_fields',
    'has_captcha',
    'audience',
    'evidence_url',
    'evidence_excerpt',
    'status',
])]
final class ScoutContactChannelEloquentModel extends Model
{
    /** @use HasFactory<ScoutContactChannelFactory> */
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
            'channel_type' => ChannelType::class,
            'form_fields' => 'array',
            'has_captcha' => 'boolean',
            'audience' => ChannelAudience::class,
            'status' => ChannelStatus::class,
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
     * @return HasMany<ScoutOutreachEloquentModel, $this>
     */
    public function outreaches(): HasMany
    {
        return $this->hasMany(ScoutOutreachEloquentModel::class, 'contact_channel_id');
    }

    protected static function newFactory(): ScoutContactChannelFactory
    {
        return ScoutContactChannelFactory::new();
    }
}
