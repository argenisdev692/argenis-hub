<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CourseScripts\Application\Commands\RequestCoursePreparationHandler;
use Modules\CourseScripts\Application\Commands\UpdateCourseBibleHandler;
use Modules\CourseScripts\Application\DTOs\CourseBibleData;
use Modules\CourseScripts\Application\DTOs\PrepareCourseData;

/**
 * The course bible and course preparation (US-3, US-4).
 */
final readonly class CourseBibleController
{
    public function update(Request $request, string $uuid, CourseBibleData $bible, UpdateCourseBibleHandler $update): JsonResponse
    {
        $user = $this->user($request);

        return response()->json(['data' => $update->handle($uuid, $bible, $user->id, $user)]);
    }

    /**
     * Queues the bible proposal (when there is none) and subject research.
     */
    public function prepare(Request $request, string $uuid, PrepareCourseData $data, RequestCoursePreparationHandler $prepare): JsonResponse
    {
        $user = $this->user($request);
        $prepare->handle($uuid, $data, $user->id, $user);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
