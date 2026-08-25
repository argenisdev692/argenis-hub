<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Spec 001 FR-18. The UI authorizes on permissions, never on
                // roles — roles are a backend grouping only. Hiding UI is
                // defence in depth; the backend stays authoritative.
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Browser key for Places Autocomplete. Public by nature — it is
            // restricted by HTTP referrer in Google Cloud, not by secrecy.
            'googleMapsKey' => config('services.google_maps.key'),
        ];
    }
}
