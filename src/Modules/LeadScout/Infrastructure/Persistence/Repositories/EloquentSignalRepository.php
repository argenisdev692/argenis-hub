<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use Modules\LeadScout\Domain\Ports\SignalRepositoryPort;
use Modules\LeadScout\Domain\ValueObjects\NewSignal;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\SignalMapper;

final readonly class EloquentSignalRepository implements SignalRepositoryPort
{
    public function forCompany(int $companyId): array
    {
        return ScoutSignalEloquentModel::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(SignalMapper::toEntity(...))
            ->values()
            ->all();
    }

    public function addIfAbsent(int $companyId, NewSignal $signal): bool
    {
        return ScoutSignalEloquentModel::query()->firstOrCreate(
            ['company_id' => $companyId, 'signal_key' => $signal->signalKey],
            [
                'dimension' => $signal->dimension->value,
                'value_text' => $signal->valueText,
                'nature' => $signal->nature->value,
                'confidence' => $signal->confidence,
                'evidence_url' => $signal->evidenceUrl,
                'evidence_excerpt' => $signal->evidenceExcerpt,
                'captured_at' => $signal->capturedAt,
                'extraction_method' => $signal->extractionMethod->value,
                'ai_provider' => $signal->aiProvider,
                'ai_model' => $signal->aiModel,
            ],
        )->wasRecentlyCreated;
    }
}
