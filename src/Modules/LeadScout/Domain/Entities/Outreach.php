<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;

/**
 * One manual approach to a company and its funnel history (spec US-5,
 * US-6). Aggregate root of its opportunities. Other aggregates are
 * referenced by identity only.
 */
final readonly class Outreach
{
    /**
     * @param  list<Opportunity>  $opportunities
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public int $companyId,
        public string $companyUuid,
        public ?int $contactId,
        public ?int $contactChannelId,
        public ?string $contactChannelUuid,
        public int $operatorId,
        public OutreachStage $stage,
        public ?OutreachChannel $sendMedium,
        public ?OutreachKind $outreachKind,
        public ?string $senderKind,
        public ?MessageVariant $variant,
        public ?string $draftBody,
        public ?string $aiProvider,
        public ?string $aiModel,
        public ?string $channelWarning,
        public ?LegalRuleStatus $legalRuleStatus,
        public ?DateTimeImmutable $sentAt,
        public ?ReplyOutcome $replyOutcome,
        public ?DateTimeImmutable $repliedAt,
        public ?DateTimeImmutable $stageChangedAt,
        public array $opportunities,
    ) {}
}
