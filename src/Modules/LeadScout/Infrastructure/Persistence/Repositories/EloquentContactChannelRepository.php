<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Modules\LeadScout\Domain\Entities\ContactChannel;
use Modules\LeadScout\Domain\Enums\ChannelAudience;
use Modules\LeadScout\Domain\Enums\ChannelStatus;
use Modules\LeadScout\Domain\Enums\ChannelType;
use Modules\LeadScout\Domain\Ports\ContactChannelRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\ContactChannelMapper;

final readonly class EloquentContactChannelRepository implements ContactChannelRepositoryPort
{
    public function byUuid(string $uuid): ?ContactChannel
    {
        $model = ScoutContactChannelEloquentModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : ContactChannelMapper::toEntity($model);
    }

    public function setStatus(ContactChannel $channel, ChannelStatus $status): ContactChannel
    {
        $model = ScoutContactChannelEloquentModel::query()->findOrFail($channel->id);
        $model->update(['status' => $status->value]);

        return ContactChannelMapper::toEntity($model);
    }

    public function forCompany(int $companyId): array
    {
        return ScoutContactChannelEloquentModel::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(ContactChannelMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function activeForCompany(int $companyId): array
    {
        return ScoutContactChannelEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('status', ChannelStatus::Active->value)
            ->orderBy('id')
            ->get()
            ->map(ContactChannelMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function upsertDetected(
        int $companyId,
        ChannelType $type,
        ?string $url,
        ?string $genericEmail,
        ?array $formFields,
        bool $hasCaptcha,
        ?ChannelAudience $audience,
        ?string $evidenceUrl,
        ?string $evidenceExcerpt,
    ): bool {
        $existing = ScoutContactChannelEloquentModel::query()
            ->where('company_id', $companyId)
            ->where('channel_type', $type->value)
            ->where('url', $url)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'generic_email' => $genericEmail ?? $existing->generic_email,
                'evidence_url' => $evidenceUrl,
                'evidence_excerpt' => $evidenceExcerpt,
            ]);

            return false;
        }

        ScoutContactChannelEloquentModel::query()->create([
            'company_id' => $companyId,
            'channel_type' => $type->value,
            'url' => $url,
            'generic_email' => $genericEmail,
            'form_fields' => $formFields,
            'has_captcha' => $hasCaptcha,
            'audience' => $audience?->value,
            'evidence_url' => $evidenceUrl,
            'evidence_excerpt' => $evidenceExcerpt,
        ]);

        return true;
    }
}
