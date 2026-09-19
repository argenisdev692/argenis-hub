<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Contact;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactEloquentModel;

final readonly class ContactMapper
{
    /**
     * Expects `company:id,canonical_domain` eager-loaded when the domain is needed.
     */
    public static function toEntity(ScoutContactEloquentModel $model): Contact
    {
        return new Contact(
            id: $model->id,
            uuid: $model->uuid,
            companyId: (int) $model->company_id,
            companyDomain: $model->relationLoaded('company') ? $model->company?->canonical_domain : null,
            fullName: $model->full_name,
            roleTitle: $model->role_title,
            roleCategory: $model->role_category,
            isPrimary: (bool) $model->is_primary,
            publishedEmail: $model->published_email,
            emailKind: $model->email_kind,
            publicProfileUrl: $model->public_profile_url,
            source: $model->source,
            evidenceUrl: $model->evidence_url,
            evidenceExcerpt: $model->evidence_excerpt,
            anonymizedAt: $model->anonymized_at?->toDateTimeImmutable(),
            contactDeadlineAt: $model->contact_deadline_at?->toDateTimeImmutable(),
            notifiedAt: $model->notified_at?->toDateTimeImmutable(),
            lastVerifiedAt: $model->last_verified_at?->toDateTimeImmutable(),
        );
    }
}
