<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\ValueObjects;

use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachKind;

/**
 * A generated draft ready to be stored in the `draft` stage (spec US-5).
 * Provider and model are frozen on the row: changing the AI default
 * never rewrites existing drafts.
 */
final readonly class NewOutreachDraft
{
    public function __construct(
        public int $companyId,
        public ?int $contactId,
        public ?int $contactChannelId,
        public int $operatorId,
        public OutreachKind $outreachKind,
        public string $senderKind,
        public string $draftBody,
        public MessageVariant $variant,
        public ?string $signalUsed,
        public string $templateKey,
        public int $templateVersion,
        public ?string $aiProvider,
        public ?string $aiModel,
        public ?string $channelWarning,
        public ?LegalRuleStatus $legalRuleStatus,
    ) {}
}
