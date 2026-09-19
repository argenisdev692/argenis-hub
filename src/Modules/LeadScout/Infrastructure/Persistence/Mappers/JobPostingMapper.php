<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\JobPosting;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;

final readonly class JobPostingMapper
{
    public static function toEntity(ScoutJobPostingEloquentModel $model): JobPosting
    {
        return new JobPosting(
            id: $model->id,
            uuid: $model->uuid,
            companyId: $model->company_id,
            fingerprint: $model->fingerprint,
            companyName: $model->company_name,
            title: $model->title,
            location: $model->location,
            country: $model->country,
            remoteMode: $model->remote_mode,
            contractType: $model->contract_type,
            language: $model->language,
            publishedAt: $model->published_at?->toDateTimeImmutable(),
            status: $model->status,
            sourceUrl: $model->source_url,
            companyUrl: $model->company_url,
            bodyText: $model->body_text,
        );
    }
}
