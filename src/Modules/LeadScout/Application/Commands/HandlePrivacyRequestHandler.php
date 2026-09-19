<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Carbon\CarbonImmutable;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\Enums\PrivacyRequestType;
use Modules\LeadScout\Domain\Ports\ContactRepositoryPort;
use Modules\LeadScout\Domain\Ports\PrivacyRequestRepositoryPort;
use Modules\LeadScout\Domain\Ports\TransactionPort;
use Modules\LeadScout\Domain\Services\DecisionMakerExtractor;

/**
 * Data-subject rights (spec FR-29, T073): search/export/erase/object by a
 * name or email fragment (min 3 chars, enforced by the command). Every
 * request is recorded in `scout_privacy_requests` with a subject hash —
 * never PII. The export returns the subject's own data; the command writes
 * it to a file outside the git-tracked tree.
 */
final readonly class HandlePrivacyRequestHandler
{
    private const int MAX_MATCHES = 100;

    public function __construct(
        private ContactRepositoryPort $contacts,
        private PrivacyRequestRepositoryPort $privacyRequests,
        private TransactionPort $transaction,
    ) {}

    /**
     * @return list<array{uuid: string, company: string, role: ?string, anonymized: bool}>
     */
    #[\NoDiscard]
    public function search(string $query): array
    {
        $rows = $this->contacts->matchingPerson($query, self::MAX_MATCHES);
        $this->ledger($query, PrivacyRequestType::Access);

        return array_map(static fn (Contact $contact): array => [
            'uuid' => $contact->uuid,
            'company' => (string) $contact->companyDomain,
            'role' => $contact->roleTitle,
            'anonymized' => $contact->isAnonymized(),
        ], $rows);
    }

    /**
     * @return list<array{uuid: string, company: string, full_name: ?string, role_title: ?string, role_category: ?string, published_email: ?string, public_profile_url: ?string, evidence_url: ?string, anonymized_at: ?string}>
     */
    #[\NoDiscard]
    public function export(string $query): array
    {
        $payload = array_map(static fn (Contact $contact): array => [
            'uuid' => $contact->uuid,
            'company' => (string) $contact->companyDomain,
            'full_name' => $contact->fullName,
            'role_title' => $contact->roleTitle,
            'role_category' => $contact->roleCategory?->value,
            'published_email' => $contact->publishedEmail,
            'public_profile_url' => $contact->publicProfileUrl,
            'evidence_url' => $contact->evidenceUrl,
            'anonymized_at' => $contact->anonymizedAt?->format(DATE_ATOM),
        ], $this->contacts->matchingPerson($query, self::MAX_MATCHES));

        $this->ledger($query, PrivacyRequestType::Access);

        return $payload;
    }

    /**
     * @return array{anonymized: int}
     */
    #[\NoDiscard]
    public function erase(string $query): array
    {
        $rows = $this->contacts->matchingPerson($query, self::MAX_MATCHES);
        $now = CarbonImmutable::now();

        $count = $this->transaction->run(function () use ($rows, $now): int {
            $count = 0;

            foreach ($rows as $contact) {
                if (! $contact->isAnonymized()) {
                    $this->contacts->anonymize($contact->id, $now);
                    $count++;
                }
            }

            return $count;
        });

        $this->ledger($query, PrivacyRequestType::Erasure);

        return ['anonymized' => $count];
    }

    /**
     * @return array{anonymized: int, objections: int}
     */
    #[\NoDiscard]
    public function object(string $query): array
    {
        $rows = $this->contacts->matchingPerson($query, self::MAX_MATCHES);
        $now = CarbonImmutable::now();

        $report = $this->transaction->run(function () use ($rows, $now): array {
            $report = ['anonymized' => 0, 'objections' => 0];

            foreach ($rows as $contact) {
                $name = (string) $contact->fullName;

                if ($name !== '' && $this->contacts->recordObjection(
                    DecisionMakerExtractor::personHash($name, (string) $contact->companyDomain),
                )) {
                    $report['objections']++;
                }

                if (! $contact->isAnonymized()) {
                    $this->contacts->anonymize($contact->id, $now);
                    $report['anonymized']++;
                }
            }

            return $report;
        });

        $this->ledger($query, PrivacyRequestType::Objection);

        return $report;
    }

    private function ledger(string $query, PrivacyRequestType $type): void
    {
        $this->privacyRequests->recordResolved(
            hash('sha256', mb_strtolower(trim($query))),
            $type,
            CarbonImmutable::now(),
        );
    }
}
