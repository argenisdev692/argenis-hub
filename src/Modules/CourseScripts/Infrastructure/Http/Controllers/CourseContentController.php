<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CourseScripts\Application\Commands\AttachSourceDocumentHandler;
use Modules\CourseScripts\Application\Commands\DetachSourceDocumentHandler;
use Modules\CourseScripts\Application\Commands\UpdateCourseNotesHandler;
use Modules\CourseScripts\Application\Commands\UpdateVideoBriefHandler;
use Modules\CourseScripts\Application\DTOs\UpdateCourseNotesData;
use Modules\CourseScripts\Application\DTOs\UpdateVideoBriefData;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Infrastructure\Http\Requests\AttachSourceDocumentRequest;

/**
 * Editing what the author gave the module: briefs, notes, content files and
 * style references (US-2, US-15).
 */
final readonly class CourseContentController
{
    public function updateVideo(Request $request, string $uuid, string $videoUuid, UpdateVideoBriefData $data, UpdateVideoBriefHandler $update): JsonResponse
    {
        return response()->json(['data' => $update->handle($uuid, $videoUuid, $data, $this->user($request)->id)]);
    }

    public function updateCourseNotes(Request $request, string $uuid, UpdateCourseNotesData $data, UpdateCourseNotesHandler $update): JsonResponse
    {
        $update->handle($uuid, $data, $this->user($request)->id);

        return response()->json(status: 204);
    }

    public function attachContent(AttachSourceDocumentRequest $request, string $uuid, AttachSourceDocumentHandler $attach): JsonResponse
    {
        return response()->json([
            'data' => $attach->handle($uuid, $request->toDocument(), SourceDocumentKind::Content, $this->user($request)->id),
        ], 201);
    }

    public function attachStyleReference(AttachSourceDocumentRequest $request, string $uuid, AttachSourceDocumentHandler $attach): JsonResponse
    {
        return response()->json([
            'data' => $attach->handle($uuid, $request->toDocument(), SourceDocumentKind::StyleReference, $this->user($request)->id),
        ], 201);
    }

    public function detachDocument(Request $request, string $uuid, string $documentUuid, DetachSourceDocumentHandler $detach): JsonResponse
    {
        $detach->handle($uuid, $documentUuid, $this->user($request)->id);

        return response()->json(status: 204);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
