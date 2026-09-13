<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CourseScripts\Application\Queries\BuildCourseBundleHandler;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Shared\Domain\Ports\StoragePort;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads (US-8 · FR-48, FR-48a, FR-51): single files through short-lived
 * signed URLs, bundles as ZIP streams of a temporary file that is deleted
 * after sending. Nothing is ever publicly addressable.
 */
final readonly class DeliverableController
{
    public function download(Request $request, string $deliverableUuid, ScriptVersionRepositoryPort $versions, StoragePort $storage): RedirectResponse|JsonResponse
    {
        $deliverable = $versions->findOwnedDeliverable($deliverableUuid, $this->user($request)->id) ?? throw new CourseNotFoundException;
        $url = $storage->temporaryUrl($deliverable->path, now()->addMinutes((int) config('course-scripts.deliverables.signed_url_minutes', 15)));

        return match ($request->expectsJson()) {
            true => response()->json(['data' => ['url' => $url]]),
            false => redirect()->away($url),
        };
    }

    public function courseBundle(Request $request, string $uuid, BuildCourseBundleHandler $bundles): BinaryFileResponse
    {
        return $this->zip($bundles->handle($uuid, $this->user($request)->id));
    }

    public function videoBundle(Request $request, string $uuid, string $videoUuid, BuildCourseBundleHandler $bundles): BinaryFileResponse
    {
        return $this->zip($bundles->handle($uuid, $this->user($request)->id, $videoUuid));
    }

    /**
     * @param  array{path: string, filename: string}  $bundle
     */
    private function zip(array $bundle): BinaryFileResponse
    {
        return response()
            ->download($bundle['path'], $bundle['filename'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
