<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Entities;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Enums\ContactSource;
use Modules\LeadScout\Domain\Enums\EmailKind;
use Modules\LeadScout\Domain\Enums\RoleCategory;

/**
 * Decisor at a Tier A/B company (spec US-11, FR-23). Personal fields are
 * null once anonymized (FR-27/28).
 *
 * `$companyDomain` travels with the contact because a person's identity
 * across the system — the objection hash — is name + company domain.
 */
final readonly class Contact
{
    public function __construct(
        public int $id,
        public string $uuid,
        public int $companyId,
        public ?string $companyDomain,
        public ?string $fullName,
        public ?string $roleTitle,
        public ?RoleCategory $roleCategory,
        public bool $isPrimary,
        public ?string $publishedEmail,
        public ?EmailKind $emailKind,
        public ?string $publicProfileUrl,
        public ContactSource $source,
        public ?string $evidenceUrl,
        public ?string $evidenceExcerpt,
        public ?DateTimeImmutable $anonymizedAt,
        public ?DateTimeImmutable $contactDeadlineAt,
        public ?DateTimeImmutable $notifiedAt,
        public ?DateTimeImmutable $lastVerifiedAt,
    ) {}

    /**
     * The contact to address: the primary one, else the first.
     *
     * @param  list<self>  $contacts
     */
    public static function primaryOf(array $contacts): ?self
    {
        return array_find($contacts, static fn (self $contact): bool => $contact->isPrimary) ?? array_first($contacts);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }
}
