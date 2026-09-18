<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Outreach response (plan §5 `OutreachData`). Draft bodies travel here —
 * never into logs (FR-35-adjacent discipline for commercial text).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class OutreachData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $companyUuid,
        public readonly string $stage,
        public readonly ?string $sendMedium,
        public readonly ?string $outreachKind,
        public readonly ?string $senderKind,
        public readonly ?string $variant,
        public readonly ?string $draftBody,
        public readonly ?string $aiProvider,
        public readonly ?string $aiModel,
        public readonly ?string $channelWarning,
        public readonly ?string $legalRuleStatus,
        public readonly ?string $sentAt,
        public readonly ?string $replyOutcome,
        public readonly ?string $stageChangedAt,
        public readonly ?string $contactChannelId = null,
        /** @var list<array{uuid: string, type: string, status: string, hours_per_month: ?int, amount_cents: ?int}> */
        public readonly array $opportunities = [],
    ) {}

    public static function fromModel(ScoutOutreachEloquentModel $outreach, string $companyUuid): self
    {
        $channel = $outreach->relationLoaded('contactChannel') ? $outreach->contactChannel : null;

        return new self(
            uuid: $outreach->uuid,
            companyUuid: $companyUuid,
            stage: $outreach->stage->value,
            sendMedium: $outreach->send_medium?->value,
            outreachKind: $outreach->outreach_kind?->value,
            senderKind: $outreach->sender_kind,
            variant: $outreach->variant?->value,
            draftBody: $outreach->draft_body,
            aiProvider: $outreach->ai_provider,
            aiModel: $outreach->ai_model,
            channelWarning: $outreach->channel_warning,
            legalRuleStatus: $outreach->legal_rule_status?->value,
            sentAt: $outreach->sent_at?->toIso8601String(),
            replyOutcome: $outreach->reply_outcome?->value,
            stageChangedAt: $outreach->stage_changed_at?->toIso8601String(),
            contactChannelId: $channel?->uuid,
            opportunities: $outreach->relationLoaded('opportunities')
                ? $outreach->opportunities->map(static fn ($opportunity): array => [
                    'uuid' => $opportunity->uuid,
                    'type' => $opportunity->type->value,
                    'status' => $opportunity->status->value,
                    'hours_per_month' => $opportunity->hours_per_month,
                    'amount_cents' => $opportunity->amount_cents,
                ])->all()
                : [],
        );
    }
}
