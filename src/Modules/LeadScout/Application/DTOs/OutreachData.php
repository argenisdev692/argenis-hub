<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\Opportunity;
use Modules\LeadScout\Domain\Entities\Outreach;
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

    public static function fromEntity(Outreach $outreach): self
    {
        return new self(
            uuid: $outreach->uuid,
            companyUuid: $outreach->companyUuid,
            stage: $outreach->stage->value,
            sendMedium: $outreach->sendMedium?->value,
            outreachKind: $outreach->outreachKind?->value,
            senderKind: $outreach->senderKind,
            variant: $outreach->variant?->value,
            draftBody: $outreach->draftBody,
            aiProvider: $outreach->aiProvider,
            aiModel: $outreach->aiModel,
            channelWarning: $outreach->channelWarning,
            legalRuleStatus: $outreach->legalRuleStatus?->value,
            sentAt: $outreach->sentAt?->format(DATE_ATOM),
            replyOutcome: $outreach->replyOutcome?->value,
            stageChangedAt: $outreach->stageChangedAt?->format(DATE_ATOM),
            contactChannelId: $outreach->contactChannelUuid,
            opportunities: array_map(static fn (Opportunity $opportunity): array => [
                'uuid' => $opportunity->uuid,
                'type' => $opportunity->type->value,
                'status' => $opportunity->status->value,
                'hours_per_month' => $opportunity->hoursPerMonth,
                'amount_cents' => $opportunity->amountCents,
            ], $outreach->opportunities),
        );
    }
}
