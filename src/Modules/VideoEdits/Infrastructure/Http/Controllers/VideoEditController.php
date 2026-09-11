<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\VideoEdits\Application\Commands\CreateVideoEditHandler;
use Modules\VideoEdits\Application\Commands\DeleteVideoEditHandler;
use Modules\VideoEdits\Application\Commands\RetryVideoEditHandler;
use Modules\VideoEdits\Application\Commands\SubmitVideoEditHandler;
use Modules\VideoEdits\Application\DTOs\CreateVideoEditData;
use Modules\VideoEdits\Application\DTOs\RetryVideoEditData;
use Modules\VideoEdits\Application\DTOs\VideoEditDetailData;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\Queries\GetVideoEditDownloadUrlHandler;
use Modules\VideoEdits\Application\Queries\GetVideoEditHandler;
use Modules\VideoEdits\Application\Queries\ListVideoEditsHandler;

/**
 * JSON surface of the Video Edits module under `/data/admin/video-edits`
 * (plan §5). Each method is guarded by its own permission and throttle at the
 * route level; ownership is enforced by the handlers. Domain exceptions are
 * rendered by VideoEditsServiceProvider.
 */
final readonly class VideoEditController
{
    public function __construct(
        private ListVideoEditsHandler $listVideoEdits,
        private GetVideoEditHandler $getVideoEdit,
        private GetVideoEditDownloadUrlHandler $getDownloadUrl,
        private CreateVideoEditHandler $createVideoEdit,
        private SubmitVideoEditHandler $submitVideoEdit,
        private RetryVideoEditHandler $retryVideoEdit,
        private DeleteVideoEditHandler $deleteVideoEdit,
    ) {}

    public function index(Request $request, VideoEditFilterData $filters): JsonResponse
    {
        return response()->json($this->listVideoEdits->handle($filters, $this->user($request)->id));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        return response()->json($this->getVideoEdit->handle($uuid, $this->user($request)->id));
    }

    public function downloadUrl(Request $request, string $uuid): JsonResponse
    {
        return response()->json($this->getDownloadUrl->handle($uuid, $this->user($request)->id));
    }

    public function store(Request $request, CreateVideoEditData $data): JsonResponse
    {
        $user = $this->user($request);

        return response()->json($this->createVideoEdit->handle($data, $user->id, $user->uuid), 201);
    }

    public function submit(Request $request, string $uuid): JsonResponse
    {
        return response()->json(
            VideoEditDetailData::fromModel($this->submitVideoEdit->handle($uuid, $this->user($request)), now()),
            202,
        );
    }

    public function retry(Request $request, string $uuid, RetryVideoEditData $data): JsonResponse
    {
        return response()->json(
            VideoEditDetailData::fromModel($this->retryVideoEdit->handle($uuid, $this->user($request), $data), now()),
            202,
        );
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->deleteVideoEdit->handle($uuid, $this->user($request));

        return response()->json(status: 204);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
