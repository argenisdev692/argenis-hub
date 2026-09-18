<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\DTOs\SourceData;
use Modules\LeadScout\Application\DTOs\UpdateSourceData;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;

/**
 * Source registry endpoints (spec US-2, plan §5).
 */
final readonly class SourceController
{
    public function index(): JsonResponse
    {
        $sources = ScoutSourceEloquentModel::query()
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => SourceData::collect($sources)]);
    }

    public function update(string $uuid, UpdateSourceData $data): JsonResponse
    {
        $source = ScoutSourceEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        $status = $data->status === null ? $source->status : SourceStatus::from($data->status);
        $termsAt = $data->termsReviewedAt ?? $source->terms_reviewed_at?->toDateTimeString();

        if ($status === SourceStatus::Active && $termsAt === null) {
            return response()->json(['message' => 'Review the source terms before activating it.', 'code' => 'TERMS_NOT_REVIEWED'], 422);
        }

        $source->update([
            'status' => $status->value,
            'frequency_minutes' => $data->frequencyMinutes ?? $source->frequency_minutes,
            'terms_reviewed_at' => $data->termsReviewedAt,
        ]);

        return response()->json(['data' => SourceData::fromModel($source->refresh())]);
    }

    public function run(string $uuid): JsonResponse
    {
        $source = ScoutSourceEloquentModel::query()->where('uuid', $uuid)->firstOrFail();

        if (cache()->has(IngestSourceJob::runningKey($source->uuid))) {
            return response()->json(['message' => 'This source is already running.', 'code' => 'SOURCE_RUNNING'], 409);
        }

        IngestSourceJob::dispatch($source->uuid);

        return response()->json(['data' => ['uuid' => $source->uuid, 'queued' => true]], 202);
    }
}
