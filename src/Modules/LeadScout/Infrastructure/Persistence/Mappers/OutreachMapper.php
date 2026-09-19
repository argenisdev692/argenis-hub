<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOpportunityEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;

final readonly class OutreachMapper
{
    /**
     * Relations every outreach read loads (identity of referenced aggregates
     * + the opportunities this aggregate owns).
     *
     * @var list<string>
     */
    public const array RELATIONS = [
        'company:id,uuid',
        'contactChannel:id,uuid',
        'opportunities:id,uuid,outreach_id,type,hours_per_month,hourly_rate_cents,amount_cents,currency,status,started_at,ended_at',
    ];

    public static function toEntity(ScoutOutreachEloquentModel $model): Outreach
    {
        return new Outreach(
            id: $model->id,
            uuid: $model->uuid,
            companyId: (int) $model->company_id,
            companyUuid: (string) $model->company?->uuid,
            contactId: $model->contact_id,
            contactChannelId: $model->contact_channel_id,
            contactChannelUuid: $model->contactChannel?->uuid,
            operatorId: (int) $model->operator_id,
            stage: $model->stage,
            sendMedium: $model->send_medium,
            outreachKind: $model->outreach_kind,
            senderKind: $model->sender_kind,
            variant: $model->variant,
            draftBody: $model->draft_body,
            aiProvider: $model->ai_provider,
            aiModel: $model->ai_model,
            channelWarning: $model->channel_warning,
            legalRuleStatus: $model->legal_rule_status,
            sentAt: $model->sent_at?->toDateTimeImmutable(),
            replyOutcome: $model->reply_outcome,
            repliedAt: $model->replied_at?->toDateTimeImmutable(),
            stageChangedAt: $model->stage_changed_at?->toDateTimeImmutable(),
            opportunities: $model->opportunities
                ->map(static fn (ScoutOpportunityEloquentModel $opportunity) => OpportunityMapper::toEntity($opportunity, $model->uuid))
                ->values()
                ->all(),
        );
    }
}
