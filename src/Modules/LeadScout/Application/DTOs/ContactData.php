<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;
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

    public static function fromModel(ScoutContactEloquentModel $contact): self
    {
        return new self(
            uuid: $contact->uuid,
            fullName: $contact->full_name,
            roleTitle: $contact->role_title,
            roleCategory: $contact->role_category?->value,
            isPrimary: (bool) $contact->is_primary,
            publishedEmail: $contact->published_email,
            emailKind: $contact->email_kind?->value,
            publicProfileUrl: $contact->public_profile_url,
            source: $contact->source->value,
            evidenceUrl: $contact->evidence_url,
            evidenceExcerpt: $contact->evidence_excerpt,
            lastVerifiedAt: $contact->last_verified_at?->toIso8601String(),
        );
    }
}
