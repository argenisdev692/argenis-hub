<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Modules\LeadScout\Domain\Exceptions\ContactNotFoundException;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\PrivacyRequestRepositoryPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;

/**
 * Person objection (spec US-11 CA-10, FR-25/29, T049): immediate
 * anonymization + opposition hash (blocks re-extraction) + privacy-ledger
 * row without personal data.
 */
final readonly class ObjectContactHandler
{
    public function __construct(
        private ContactRepositoryPort $contacts,
        private PrivacyRequestRepositoryPort $privacyRequests,
        private TransactionPort $transaction,
    ) {}

    public function handle(string $contactUuid): void
    {
        $contact = $this->contacts->byUuid($contactUuid) ?? throw new ContactNotFoundException($contactUuid);
        $hash = DecisionMakerExtractor::personHash((string) $contact->fullName, (string) $contact->companyDomain);
        $now = CarbonImmutable::now();

        $this->transaction->run(function () use ($contact, $hash, $now): void {
            $this->contacts->recordObjection($hash);
            $this->contacts->anonymize($contact->id, $now);
            $this->privacyRequests->recordResolved($hash, PrivacyRequestType::Objection, $now);
        });
    }
}
