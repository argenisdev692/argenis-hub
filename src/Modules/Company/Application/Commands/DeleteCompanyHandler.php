<?php

declare(strict_types=1);

namespace Modules\Company\Application\Commands;

use Modules\Company\Domain\Ports\CompanyRepositoryPort;

/**
 * Soft-deletes the singleton company record.
 *
 * A reversible admin action: the row keeps its `deleted_at` and is brought back
 * by `RestoreCompanyHandler`. No manual audit call — the `deleted` event is
 * exactly what the `LogsActivity` trait on `App\Models\CompanyData` records,
 * under the `company.data` log name (same reasoning as `UpdateCompanyHandler`).
 *
 * While the record is trashed every branding consumer (the shared
 * `CompanyProfile` kernel service) falls back to the app's own name, URL and
 * bundled logos — the repository's `deleted` hook flushes the caches that would
 * otherwise still hold the old values.
 */
final readonly class DeleteCompanyHandler
{
    public function __construct(private CompanyRepositoryPort $companies) {}

    public function handle(): void
    {
        $this->companies->delete();
    }
}
