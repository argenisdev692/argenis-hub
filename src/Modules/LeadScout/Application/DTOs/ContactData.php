<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Entities\Contact;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Decisor response (plan §5 `ContactData`): evidence included, cold-email
 * usability left to `ChannelAdvisor` (Phase H) — never decided here.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ContactData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $fullName,
        public readonly ?string $roleTitle,
        public readonly ?string $roleCategory,
        public readonly bool $isPrimary,
        public readonly ?string $publishedEmail,
        public readonly ?string $emailKind,
        public readonly ?string $publicProfileUrl,
        public readonly string $source,
        public readonly ?string $evidenceUrl,
        public readonly ?string $evidenceExcerpt,
        public readonly ?string $lastVerifiedAt,
    ) {}

    public static function fromEntity(Contact $contact): self
    {
        return new self(
            uuid: $contact->uuid,
            fullName: $contact->fullName,
            roleTitle: $contact->roleTitle,
            roleCategory: $contact->roleCategory?->value,
            isPrimary: $contact->isPrimary,
            publishedEmail: $contact->publishedEmail,
            emailKind: $contact->emailKind?->value,
            publicProfileUrl: $contact->publicProfileUrl,
            source: $contact->source->value,
            evidenceUrl: $contact->evidenceUrl,
            evidenceExcerpt: $contact->evidenceExcerpt,
            lastVerifiedAt: $contact->lastVerifiedAt?->format(DATE_ATOM),
        );
    }
}
