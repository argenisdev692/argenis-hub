<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\ScoutOutreachFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Manual outreach record (spec US-5, US-6, FR-41/42). The module never sends:
 * `operator_id` is always the authenticated user, never client input, and
 * `draft_body` is excluded from the audit trail.
 *
 * @property int $id
 * @property string $uuid
 * @property-read ScoutCompanyEloquentModel $company
 * @property-read User $operator
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_outreaches')]
#[Fillable([
    'uuid',
    'company_id',
    'contact_id',
    'contact_channel_id',
    'send_medium',
    'outreach_kind',
    'template_key',
    'template_version',
    'operator_id',
    'sender_kind',
    'stage',
    'draft_body',
    'variant',
    'signal_used',
    'ai_provider',
    'ai_model',
    'channel_warning',
    'legal_rule_status',
    'legal_ack_at',
    'sent_at',
    'stage_changed_at',
    'reply_outcome',
    'replied_at',
    'notes',
])]
final class ScoutOutreachEloquentModel extends Model
{
    /** @use HasFactory<ScoutOutreachFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id', 'draft_body'];

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
            'send_medium' => OutreachChannel::class,
            'outreach_kind' => OutreachKind::class,
            'template_version' => 'integer',
            'stage' => OutreachStage::class,
            'variant' => MessageVariant::class,
            'legal_rule_status' => LegalRuleStatus::class,
            'legal_ack_at' => 'datetime',
            'sent_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'reply_outcome' => ReplyOutcome::class,
            'replied_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['stage', 'send_medium', 'sent_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.outreach');
    }

    /**
     * @return BelongsTo<ScoutCompanyEloquentModel, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(ScoutCompanyEloquentModel::class, 'company_id');
    }

    /**
     * @return BelongsTo<ScoutContactEloquentModel, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ScoutContactEloquentModel::class, 'contact_id');
    }

    /**
     * @return BelongsTo<ScoutContactChannelEloquentModel, $this>
     */
    public function contactChannel(): BelongsTo
    {
        return $this->belongsTo(ScoutContactChannelEloquentModel::class, 'contact_channel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * @return HasMany<ScoutOutreachStageEventEloquentModel, $this>
     */
    public function stageEvents(): HasMany
    {
        return $this->hasMany(ScoutOutreachStageEventEloquentModel::class, 'outreach_id');
    }

    /**
     * @return HasMany<ScoutOpportunityEloquentModel, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(ScoutOpportunityEloquentModel::class, 'outreach_id');
    }

    protected static function newFactory(): ScoutOutreachFactory
    {
        return ScoutOutreachFactory::new();
    }
}
