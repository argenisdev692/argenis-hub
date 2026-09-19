<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Modules\LeadScout\Application\DTOs\RecordReplyData;
use Modules\LeadScout\Domain\Entities\Outreach;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\OutreachStage;
use Modules\LeadScout\Domain\Enums\ReplyOutcome;
use Modules\LeadScout\Domain\Enums\SuppressionSource;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\OutreachRepositoryPort;
use Modules\LeadScout\Domain\Ports\SuppressionRepositoryPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;

/**
 * Reply record with absolute-unsubscribe semantics (spec FR-42/FR-43,
 * T084): `interested → positive`, `not_interested → lost`, `unsubscribe →
 * do_not_contact` + company suppression on every channel (+ the person's
 * opposition when the mailbox was nominative). After an unsubscribe, no
 * entry — discovery, import, manual, offers, re-score — may propose the
 * company again, and no web route lifts it.
 */
final readonly class RecordReplyHandler
{
    private const string UNSUBSCRIBE_REASON = 'Unsubscribe — absolute precedence (FR-43).';

    public function __construct(
        private OutreachRepositoryPort $outreaches,
        private CompanyRepositoryPort $companies,
        private ContactRepositoryPort $contacts,
        private SuppressionRepositoryPort $suppressions,
        private ObjectContactHandler $objectContact,
        private TransactionPort $transaction,
    ) {}

    public function handle(string $outreachUuid, RecordReplyData $data, int $operatorId): Outreach
    {
        $outreach = $this->outreaches->byUuid($outreachUuid)
            ?? throw InvalidInputException::withMessages(['outreach' => 'Outreach not found.']);

        if ($outreach->stage !== OutreachStage::Sent) {
            throw InvalidInputException::withMessages(['stage' => 'Only a sent outreach can receive a reply.']);
        }

        $outcome = ReplyOutcome::from($data->outcome);
        $repliedAt = self::repliedAt($data->repliedAt);

        $to = match ($outcome) {
            ReplyOutcome::Interested => OutreachStage::Positive,
            ReplyOutcome::NotInterested => OutreachStage::Lost,
            ReplyOutcome::Unsubscribe => OutreachStage::DoNotContact,
        };

        return $this->transaction->run(function () use ($outreach, $data, $operatorId, $outcome, $to, $repliedAt): Outreach {
            if ($data->notes !== null) {
                $outreach = $this->outreaches->annotate($outreach, null, mb_substr(trim($data->notes), 0, 2000));
            }

            $moved = $this->outreaches->moveToStage($outreach, $to, $operatorId, $outcome, $repliedAt);

            if ($outcome === ReplyOutcome::Unsubscribe) {
                $this->suppress($moved);
            }

            return $moved;
        });
    }

    private static function repliedAt(?string $input): ?DateTimeImmutable
    {
        if ($input === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($input)->toDateTimeImmutable();
        } catch (\Exception) {
            throw InvalidInputException::withMessages(['replied_at' => 'Invalid date.']);
        }
    }

    private function suppress(Outreach $outreach): void
    {
        $company = $this->companies->byId($outreach->companyId);

        if ($company !== null) {
            $this->suppressions->suppressDomain($company->canonicalDomain, SuppressionSource::Objection, self::UNSUBSCRIBE_REASON);
        }

        $contact = $outreach->contactId === null ? null : $this->contacts->byId($outreach->contactId);

        if ($contact !== null && $contact->emailKind === EmailKind::Nominative && $contact->publishedEmail !== null) {
            $this->objectContact->handle($contact->uuid);
        }
    }
}
