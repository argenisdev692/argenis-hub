<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CourseScripts\Application\Commands\DeleteCourseHandler;
use Modules\CourseScripts\Application\Commands\StoreCourseHandler;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Application\Queries\GetCourseHandler;
use Modules\CourseScripts\Application\Queries\ListCoursesHandler;
use Modules\CourseScripts\Infrastructure\Http\Requests\StoreCourseRequest;

/**
 * Course resource over HTTP (US-1, US-10, US-11). Serves Inertia pages and JSON
 * from one class; the branch is serialization only (Controller Fusion Rule).
 * Permissions and throttles are declared on the routes; ownership is enforced
 * by the handlers.
 */
final readonly class CourseController
{
    public function index(Request $request, ListCoursesHandler $list): InertiaResponse|JsonResponse
    {
        $filters = CourseFilterData::validateAndCreate($request);
        $courses = $list->handle($filters, $this->user($request)->id);

        return match ($request->expectsJson()) {
            true => response()->json($courses),
            false => Inertia::render('course-scripts/Index', [
                'courses' => $courses,
                'filters' => $filters,
            ]),
        };
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('course-scripts/Create', [
            'limits' => [
                'max_kb' => (int) config('course-scripts.uploads.max_kb'),
                'max_content_files' => (int) config('course-scripts.uploads.max_content_files'),
                'allowed_extensions' => (array) config('course-scripts.uploads.allowed_extensions'),
            ],
        ]);
    }

    public function store(StoreCourseRequest $request, StoreCourseHandler $store, GetCourseHandler $get): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $course = $store->handle($request->toInput(), $user->id, $user);

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $get->handle($course->uuid, $user->id)], 201),
            false => redirect()
                ->route('course-scripts.show', $course->uuid)
                ->with('success', 'Course uploaded.'),
        };
    }

    public function show(Request $request, string $uuid, GetCourseHandler $get): InertiaResponse|JsonResponse
    {
        $course = $get->handle($uuid, $this->user($request)->id);

        return match ($request->expectsJson()) {
            true => response()->json(['data' => $course]),
            false => Inertia::render('course-scripts/Show', ['course' => $course]),
        };
    }

    public function destroy(Request $request, string $uuid, DeleteCourseHandler $delete): RedirectResponse|JsonResponse
    {
        $user = $this->user($request);
        $delete->handle($uuid, $user->id, $user);

        return match ($request->expectsJson()) {
            true => response()->json(status: 204),
            false => redirect()->route('course-scripts.index')->with('success', 'Course deleted.'),
        };
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
