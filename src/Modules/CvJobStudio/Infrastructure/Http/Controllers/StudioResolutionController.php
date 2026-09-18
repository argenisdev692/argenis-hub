<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\CvJobStudio\Application\Commands\ResolvePostingSourceHandler;
use Modules\CvJobStudio\Application\Commands\UnlinkResolutionHandler;

/** Link / unlink a listing posting to the employer's own copy (T-118, T-119). */
final readonly class StudioResolutionController
{
    public function resolve(Request $request, string $uuid, ResolvePostingSourceHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Resolution evaluated.'));
    }

    public function unlink(Request $request, string $uuid, UnlinkResolutionHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $this->ownerId($request));

        return back()->with('success', __('Resolution unlinked.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
