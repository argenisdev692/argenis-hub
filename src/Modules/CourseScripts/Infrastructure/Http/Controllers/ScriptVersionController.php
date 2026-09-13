<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\CourseScripts\Application\Commands\ScriptVersionCommandsHandler;
use Modules\CourseScripts\Application\DTOs\GenerationRunData;
use Modules\CourseScripts\Application\DTOs\RegenerateScriptData;
use Modules\CourseScripts\Application\DTOs\ScriptVersionData;
use Modules\CourseScripts\Application\DTOs\ScriptVersionSummaryData;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;

/**
 * A video's script: preview, versions, regenerate with feedback, force a
 * practice pack, accept a version (US-5, US-7, US-14).
 */
final readonly class ScriptVersionController
{
    public function show(Request $request, string $uuid, string $videoUuid, ScriptVersionRepositoryPort $versions): JsonResponse
    {
        $versionUuid = $request->query('version');
        $version = $versions->findOwned($uuid, $videoUuid, $this->user($request)->id, is_string($versionUuid) && Str::isUuid($versionUuid) ? $versionUuid : null)
            ?? throw new CourseNotFoundException;

        return response()->json(['data' => ScriptVersionData::fromModel($version)]);
    }

    public function versions(Request $request, string $uuid, string $videoUuid, CourseRepositoryPort $courses, ScriptVersionRepositoryPort $versions): JsonResponse
    {
        $video = $courses->findOwnedVideo($uuid, $videoUuid, $this->user($request)->id) ?? throw new CourseNotFoundException;

        return response()->json(['data' => array_map(
            ScriptVersionSummaryData::fromModel(...),
            $versions->listForVideo($video->id),
        )]);
    }

    public function regenerate(Request $request, string $uuid, string $videoUuid, RegenerateScriptData $data, ScriptVersionCommandsHandler $commands): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => GenerationRunData::fromModel($commands->regenerate($uuid, $videoUuid, $data, $user->id, causer: $user))], 202);
    }

    public function forcePractice(Request $request, string $uuid, string $videoUuid, RegenerateScriptData $data, ScriptVersionCommandsHandler $commands): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => GenerationRunData::fromModel($commands->regenerate($uuid, $videoUuid, $data, $user->id, forcePractice: true, causer: $user))], 202);
    }

    public function accept(Request $request, string $uuid, string $videoUuid, string $versionUuid, ScriptVersionCommandsHandler $commands): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => ScriptVersionData::fromModel($commands->accept($uuid, $videoUuid, $versionUuid, $user->id, $user))]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
