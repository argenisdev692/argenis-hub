<?php

declare(strict_types=1);

namespace Modules\Company\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Company\Application\Commands\DeleteCompanyHandler;
use Modules\Company\Application\Commands\RestoreCompanyHandler;
use Modules\Company\Application\Commands\UpdateCompanyHandler;
use Modules\Company\Application\Commands\UpdateCompanyLogosHandler;
use Modules\Company\Application\DTOs\UpdateCompanyData;
use Modules\Company\Application\Queries\CompanyIsRestorableHandler;
use Modules\Company\Application\Queries\GetCompanyHandler;
use Modules\Company\Domain\Exceptions\CompanyNotConfigured;
use Modules\Company\Infrastructure\Http\Requests\UpdateCompanyLogosRequest;

/**
 * The authenticated company settings surface.
 *
 * A singleton, so there is no index and no {uuid}: every action operates on the
 * one record. There is no store() — the row is provisioned by CompanySeeder.
 *
 * There IS a destroy()/restore() pair: a reversible soft delete guarded by
 * DELETE_COMPANY_DATA / RESTORE_COMPANY_DATA (SUPER_ADMIN only). While the
 * record is trashed every branding consumer falls back to the app defaults
 * (see Shared\Infrastructure\Company\CompanyProfile) and show() renders the
 * "deleted, restore it" screen instead of the record.
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
        private CompanyIsRestorableHandler $companyIsRestorable,
        private UpdateCompanyHandler $updateCompany,
        private UpdateCompanyLogosHandler $updateLogos,
        private DeleteCompanyHandler $deleteCompany,
        private RestoreCompanyHandler $restoreCompany,
    ) {}

    /**
     * Read-only view of the company record.
     *
     * When the record is soft-deleted, an operator who can restore it gets the
     * dedicated "deleted" screen; everyone else (and every JSON client) gets the
     * same 404 as a never-seeded installation.
     */
    public function show(Request $request): InertiaResponse|JsonResponse
    {
        try {
            $company = $this->getCompany->handle();
        } catch (CompanyNotConfigured $e) {
            if ($request->expectsJson() || ! $this->companyIsRestorable->handle()) {
                throw $e;
            }

            return Inertia::render('settings/company/Deleted');
        }

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

    /**
     * Soft-delete the company record. Reversible via {@see restore()}.
     */
    public function destroy(Request $request): RedirectResponse|JsonResponse
    {
        $this->deleteCompany->handle();

        return $request->expectsJson()
            ? response()->json(null, 204)
            : redirect()->route('dashboard')->with('status', 'company-deleted');
    }

    /**
     * Bring a soft-deleted company record back.
     */
    public function restore(Request $request): RedirectResponse|JsonResponse
    {
        $company = $this->restoreCompany->handle();

        return $request->expectsJson()
            ? response()->json($company)
            : redirect()->route('company.show')->with('status', 'company-restored');
    }
}
