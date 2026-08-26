<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Company\Application\Commands\UpdateCompanyHandler;
use Modules\Company\Application\Commands\UpdateCompanyLogosHandler;
use Modules\Company\Application\DTOs\UpdateCompanyData;
use Modules\Company\Application\Queries\GetCompanyHandler;
use Modules\Company\Infrastructure\Http\Requests\UpdateCompanyLogosRequest;

/**
 * The authenticated company settings surface.
 *
 * A singleton, so there is no index and no {uuid}: every action operates on the
 * one record. There is no store() and no destroy() either — the row is
 * provisioned by CompanySeeder and outlives the installation, and an endpoint
 * that could delete it would be a foot-gun with no use-case behind it.
 *
 * One fused controller serves Inertia and JSON (Controller Fusion Rule): the
 * authorization, the validation and the handler call are identical across both,
 * and only the final line differs. If that ever stops being true this splits
 * into Web/ and Api/.
 */
final readonly class CompanyController
{
    public function __construct(
        private GetCompanyHandler $getCompany,
        private UpdateCompanyHandler $updateCompany,
        private UpdateCompanyLogosHandler $updateLogos,
    ) {}

    /**
     * Read-only view of the company record.
     */
    public function show(Request $request): InertiaResponse|JsonResponse
    {
        $company = $this->getCompany->handle();

        return $request->expectsJson()
            ? response()->json($company)
            : Inertia::render('settings/company/Show', ['company' => $company]);
    }

    /**
     * The edit form.
     */
    public function edit(Request $request): InertiaResponse|JsonResponse
    {
        $company = $this->getCompany->handle();

        return $request->expectsJson()
            ? response()->json($company)
            : Inertia::render('settings/company/Edit', [
                'company' => $company,
                // The browser key for Places Autocomplete. Public by design (it
                // ships in the page) and locked to an HTTP referrer in Google
                // Cloud — see the note in config/services.php.
                'google_maps_key' => (string) config('services.google_maps.key'),
            ]);
    }

    /**
     * Apply an edit. The Data object validates itself as it is resolved.
     */
    public function update(Request $request, UpdateCompanyData $data): RedirectResponse|JsonResponse
    {
        $company = $this->updateCompany->handle($data);

        return $request->expectsJson()
            ? response()->json($company)
            : back()->with('status', 'company-updated');
    }

    /**
     * Replace one or more brand marks.
     */
    public function updateLogos(UpdateCompanyLogosRequest $request): RedirectResponse|JsonResponse
    {
        $logos = $this->updateLogos->handle($request->logos());

        return $request->expectsJson()
            ? response()->json($logos)
            : back()->with('status', 'company-logos-updated');
    }
}
