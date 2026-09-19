<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;

final readonly class ContactChannelMapper
{
    public static function toEntity(ScoutContactChannelEloquentModel $model): ContactChannel
    {
        return new ContactChannel(
            id: $model->id,
            uuid: $model->uuid,
            companyId: (int) $model->company_id,
            channelType: $model->channel_type,
            url: $model->url,
            genericEmail: $model->generic_email,
            formFields: $model->form_fields,
            hasCaptcha: (bool) $model->has_captcha,
            audience: $model->audience,
            evidenceUrl: $model->evidence_url,
            evidenceExcerpt: $model->evidence_excerpt,
            status: $model->status,
        );
    }
}
