<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\DiscoverAgenciesJob;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;
use Modules\LeadScout\Infrastructure\Queue\ScoreCompanyJob;

/**
 * On-demand triggers for the scheduled pipeline jobs, so the panel can run
 * them whenever the operator wants instead of waiting for the next tick.
 * Every endpoint only queues work and answers 202 — the jobs themselves are
 * the same ones `routes/console.php` schedules.
 */
final readonly class LeadScoutJobController
{
    /**
     * Queues every active source now, ignoring `frequency_minutes` (that is
     * the scheduler's concern). Sources already in flight are skipped.
     */
    public function ingest(): JsonResponse
    {
        $queued = 0;

        foreach (ScoutSourceEloquentModel::query()->where('status', 'active')->pluck('uuid') as $uuid) {
            if (cache()->has(IngestSourceJob::runningKey($uuid))) {
                continue;
            }

            IngestSourceJob::dispatch($uuid);
            $queued++;
        }

        return response()->json(['data' => ['queued' => $queued]], 202);
    }

    public function discover(): JsonResponse
    {
        DiscoverAgenciesJob::dispatch();

        return response()->json(['data' => ['queued' => 1]], 202);
    }

    /**
     * Queues a deterministic re-score for every company with stored signals
     * — the async twin of `lead-scout:score`.
     */
    public function rescore(): JsonResponse
    {
        $queued = 0;

        $companies = ScoutCompanyEloquentModel::query()->select(['id', 'uuid'])->whereHas('signals')->lazyById(500);

        foreach ($companies as $company) {
            ScoreCompanyJob::dispatch($company->uuid);
            $queued++;
        }

        return response()->json(['data' => ['queued' => $queued]], 202);
    }
}
