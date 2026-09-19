<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\UpdateSourceHandler;
use Modules\LeadScout\Application\DTOs\SourceData;
use Modules\LeadScout\Application\DTOs\UpdateSourceData;
use Modules\LeadScout\Application\Queries\ListSourcesHandler;
use Modules\LeadScout\Domain\Exceptions\SourceNotFoundException;
use Modules\LeadScout\Domain\Ports\SourceRepositoryPort;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;

/**
 * Source registry endpoints (spec US-2, plan §5).
 */
final readonly class SourceController
{
    public function index(ListSourcesHandler $list): JsonResponse
    {
        return response()->json(['data' => array_map(SourceData::fromEntity(...), $list->handle())]);
    }

    public function update(string $uuid, UpdateSourceData $data, UpdateSourceHandler $update): JsonResponse
    {
        return response()->json(['data' => SourceData::fromEntity($update->handle($uuid, $data))]);
    }

    /**
     * Queues an ingest run; the queue's running marker answers 409 while
     * one is in flight (T028).
     */
    public function run(string $uuid, SourceRepositoryPort $sources): JsonResponse
    {
        $source = $sources->byUuid($uuid) ?? throw new SourceNotFoundException($uuid);

        if (cache()->has(IngestSourceJob::runningKey($source->uuid))) {
            return response()->json(['message' => 'This source is already running.', 'code' => 'SOURCE_RUNNING'], 409);
        }

        IngestSourceJob::dispatch($source->uuid);

        return response()->json(['data' => ['uuid' => $source->uuid, 'queued' => true]], 202);
    }
}
