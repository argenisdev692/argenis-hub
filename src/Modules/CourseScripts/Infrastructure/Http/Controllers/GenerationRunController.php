<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CourseScripts\Application\Commands\ManageGenerationRunHandler;
use Modules\CourseScripts\Application\Commands\StartGenerationRunHandler;
use Modules\CourseScripts\Application\DTOs\EstimateRunData;
use Modules\CourseScripts\Application\DTOs\GenerationRunData;
use Modules\CourseScripts\Application\DTOs\StartGenerationRunData;
use Modules\CourseScripts\Application\Queries\EstimateRunCallsHandler;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;

/**
 * Generation runs: estimate, start (with or without the second review), live
 * status, cancel, retry failed (US-9, US-12, US-13).
 */
final readonly class GenerationRunController
{
    public function estimate(Request $request, string $uuid, EstimateRunData $data, EstimateRunCallsHandler $estimates): JsonResponse
    {
        $estimate = $estimates->handle($uuid, $data, $this->user($request)->id);

        return response()->json(['data' => [
            ...$estimate->confirmation(),
            'ai_calls' => $estimate->aiCalls(),
            'ai_ceiling' => $estimate->aiCeiling,
            'research_ceiling' => $estimate->researchCeiling,
            'fits' => $estimate->fits(),
            'with_review' => $data->reviewRequested(),
        ]]);
    }

    public function start(Request $request, string $uuid, StartGenerationRunData $data, StartGenerationRunHandler $start): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => GenerationRunData::fromModel($start->handle($uuid, $data, $user->id, $user))], 202);
    }

    public function show(Request $request, string $runUuid, GenerationRunRepositoryPort $runs, GenerationDispatcherPort $dispatcher): JsonResponse
    {
        $run = $runs->findOwned($runUuid, $this->user($request)->id) ?? throw new CourseNotFoundException;
        $progress = $run->status->isActive() && $run->batch_id !== null ? $dispatcher->progress($run->batch_id) : null;

        return response()->json(['data' => GenerationRunData::fromModel($run, $progress)]);
    }

    public function cancel(Request $request, string $runUuid, ManageGenerationRunHandler $runs): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => GenerationRunData::fromModel($runs->cancel($runUuid, $user->id, $user))]);
    }

    public function retryFailed(Request $request, string $runUuid, ManageGenerationRunHandler $runs): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => GenerationRunData::fromModel($runs->retryFailed($runUuid, $user->id, $user))], 202);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
