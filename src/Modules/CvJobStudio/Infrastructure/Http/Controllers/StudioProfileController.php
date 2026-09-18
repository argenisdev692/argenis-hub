<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CvJobStudio\Application\Commands\UpdateOpportunityPolicyHandler;
use Modules\CvJobStudio\Application\Commands\UpsertProfileHandler;
use Modules\CvJobStudio\Application\DTOs\StudioProfileData;
use Modules\CvJobStudio\Application\DTOs\UpdateOpportunityPolicyData;
use Modules\CvJobStudio\Application\Queries\ListProfilesHandler;

/**
 * Search-profile CRUD (v1 seeds the fullstack profile only; a second profile
 * is data, never schema — SC-7). Authorization via
 * `permission:*_STUDIO_PROFILES`, ownership via `$userId` (OWASP §11).
 */
final readonly class StudioProfileController
{
    public function index(Request $request, ListProfilesHandler $list): InertiaResponse|JsonResponse
    {
        $profiles = $list->handle(
            $this->ownerId($request),
            min(max($request->integer('per_page', 15), 1), 100),
        );

        $props = ['profiles' => StudioProfileData::collect($profiles)];

        return match ($request->expectsJson()) {
            true => response()->json($props['profiles']),
            false => Inertia::render('cv-studio/Settings/Profile', $props),
        };
    }

    public function store(Request $request, StudioProfileData $data, UpsertProfileHandler $upsert): RedirectResponse
    {
        (void) $upsert->handle($data, $this->ownerId($request));

        return back()->with('success', __('Profile saved.'));
    }

    /**
     * Opportunity policy edit (T-135, FR-51): the handler validates every
     * factor through the policy object before it touches the profile.
     * Neutral toggle included.
     */
    public function updatePolicy(Request $request, string $uuid, UpdateOpportunityPolicyData $data, UpdateOpportunityPolicyHandler $handler): RedirectResponse
    {
        (void) $handler->handle($uuid, $data, $this->ownerId($request));

        return back()->with('success', __('Opportunity policy updated.'));
    }

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }
}
