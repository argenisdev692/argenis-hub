<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ScoutContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\ContactSource;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\RoleCategory;

/**
 * Decision-maker only (spec US-11, FR-23/24): allowed role list, published
 * email on the company domain, linked profile URL with evidence. Persisted
 * for Tier A/B leads only; anonymized per FR-27/28. Images never processed.
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_contacts')]
#[Fillable([
    'uuid',
    'company_id',
    'full_name',
    'role_title',
    'role_category',
    'is_primary',
    'published_email',
    'email_kind',
    'public_profile_url',
    'source',
    'evidence_url',
    'evidence_excerpt',
    'evidence_captured_at',
    'anonymized_at',
    'contact_deadline_at',
    'notified_at',
    'last_verified_at',
])]
final class ScoutContactEloquentModel extends Model
{
    /** @use HasFactory<ScoutContactFactory> */
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
            'role_category' => RoleCategory::class,
            'is_primary' => 'boolean',
            'email_kind' => EmailKind::class,
            'source' => ContactSource::class,
            'evidence_captured_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'contact_deadline_at' => 'datetime',
            'notified_at' => 'datetime',
            'last_verified_at' => 'datetime',
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
        return $this->hasMany(ScoutOutreachEloquentModel::class, 'contact_id');
    }

    protected static function newFactory(): ScoutContactFactory
    {
        return ScoutContactFactory::new();
    }
}
