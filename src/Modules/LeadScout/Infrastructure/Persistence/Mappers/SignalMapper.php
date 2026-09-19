<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Signal;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;

final readonly class SignalMapper
{
    public static function toEntity(ScoutSignalEloquentModel $model): Signal
    {
        return new Signal(
            id: $model->id,
            companyId: (int) $model->company_id,
            dimension: $model->dimension,
            signalKey: $model->signal_key,
            valueText: $model->value_text,
            nature: $model->nature,
            confidence: (int) $model->confidence,
            evidenceUrl: $model->evidence_url,
            evidenceExcerpt: $model->evidence_excerpt,
            capturedAt: $model->captured_at?->toDateTimeImmutable(),
            extractionMethod: $model->extraction_method,
            aiProvider: $model->ai_provider,
            aiModel: $model->ai_model,
        );
    }
}
