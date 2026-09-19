<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Domain\ValueObjects\ContactDetails;

interface ContactRepositoryPort
{
    public function byUuid(string $uuid): ?Contact;

    public function byId(int $id): ?Contact;

    public function createManual(int $companyId, ContactDetails $details, DateTimeImmutable $verifiedAt): Contact;

    /**
     * Marking a contact primary clears the flag on its company's other contacts.
     */
    public function updateDetails(Contact $contact, ContactDetails $details, DateTimeImmutable $verifiedAt): Contact;

    /**
     * Blanks every personal field; the role is kept for metrics (FR-27).
     */
    public function anonymize(int $contactId, DateTimeImmutable $at): void;

    /**
     * Contacts whose name or published email contains `$query`.
     *
     * @return list<Contact>
     */
    public function matchingPerson(string $query, int $limit): array;

    /**
     * Non-anonymized contacts with their latest outreach touch, streamed.
     *
     * @return iterable<int, array{contact: Contact, lastTouchAt: ?DateTimeImmutable}>
     */
    public function retentionCandidates(): iterable;

    /**
     * Non-anonymized contacts of a company.
     *
     * @return list<Contact>
     */
    public function liveForCompany(int $companyId): array;

    public function markVerified(int $contactId, DateTimeImmutable $at): void;

    public function markNotified(int $contactId, DateTimeImmutable $at): void;

    /**
     * Anonymizes every live contact of a company (tier dropped below B).
     *
     * @return int contacts anonymized
     */
    public function anonymizeCompany(int $companyId, DateTimeImmutable $at): int;

    public function hasLiveContactNamed(int $companyId, string $fullName): bool;

    /**
     * Stores a decisor extracted from the company pages (spec US-11).
     *
     * @param  array{name: string, title: string, category: string, email: ?string, email_kind: ?string, profile_url: ?string, evidence_url: string, excerpt: string, captured_at: string}  $candidate
     */
    public function createExtracted(int $companyId, array $candidate, bool $isPrimary, DateTimeImmutable $contactDeadline): void;

    /**
     * @return list<string>
     */
    public function objectionHashes(): array;

    public function isPersonOpposed(string $personHash): bool;

    /**
     * @return bool true when the objection is new
     */
    public function recordObjection(string $personHash): bool;
}
