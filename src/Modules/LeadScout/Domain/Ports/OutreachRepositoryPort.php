<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Domain\Enums\LegalRuleStatus;
use Modules\LeadScout\Domain\Enums\MessageVariant;
use Modules\LeadScout\Domain\Enums\OutreachChannel;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Domain\ValueObjects\NewOutreachDraft;

interface OutreachRepositoryPort
{
    public function byUuid(string $uuid): ?Outreach;

    /**
     * @return list<Outreach> newest first
     */
    public function forCompany(int $companyId): array;

    public function createDraft(NewOutreachDraft $draft): Outreach;

    /**
     * Records a contact made before the system existed (spec FR-20): a
     * `sent` outreach dated at the real send, its history event, and an
     * optional move to the stage it had already reached.
     */
    public function recordImportedContact(
        int $companyId,
        int $operatorId,
        OutreachChannel $medium,
        ?MessageVariant $variant,
        string $senderKind,
        DateTimeImmutable $sentAt,
        OutreachStage $reachedStage,
        string $notes,
    ): Outreach;

    /**
     * Stores the edited draft body and/or notes; null leaves a field as is.
     */
    public function annotate(Outreach $outreach, ?string $draftBody, ?string $notes): Outreach;

    /**
     * Records a manual send (spec FR-41): channel, medium, mailbox kind and
     * the legal state the operator acknowledged.
     */
    public function recordSend(
        Outreach $outreach,
        int $contactChannelId,
        OutreachChannel $medium,
        string $senderKind,
        ?LegalRuleStatus $legalRuleStatus,
        ?DateTimeImmutable $legalAckAt,
        DateTimeImmutable $sentAt,
    ): Outreach;

    /**
     * Moves the outreach and appends the stage-history event atomically.
     * A reply outcome also stamps `replied_at` (the given time, or now).
     */
    public function moveToStage(
        Outreach $outreach,
        OutreachStage $to,
        int $operatorId,
        ?ReplyOutcome $replyOutcome = null,
        ?DateTimeImmutable $repliedAt = null,
    ): Outreach;

    public function countSentOn(int $operatorId, DateTimeImmutable $day): int;

    /** Outreaches actually sent (the funnel sample, spec US-6). */
    public function countSent(): int;

    /**
     * @param  list<OutreachStage>  $stages
     */
    public function countInStages(array $stages): int;
}
