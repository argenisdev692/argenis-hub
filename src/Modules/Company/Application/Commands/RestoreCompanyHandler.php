<?php

declare(strict_types=1);

namespace Modules\Company\Application\Commands;

use Modules\Company\Application\DTOs\CompanyProfileData;
use Modules\Company\Domain\Exceptions\CompanyNotConfigured;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;

/**
 * Restores the most recently soft-deleted company record.
 *
 * The inverse of `DeleteCompanyHandler`. The `restored` event is recorded by
 * the model's `LogsActivity` trait; the repository's `restored` hook flushes the
 * branding caches so emails, PDFs and the public payload pick the row back up.
 */
final readonly class RestoreCompanyHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private CompanyLogoStoragePort $logos,
    ) {}

    /**
     * @throws CompanyNotConfigured when there is no soft-deleted record to restore
     */
    #[\NoDiscard('handle() returns the restored company profile.')]
    public function handle(): CompanyProfileData
    {
        $company = $this->companies->restore();

        return CompanyProfileData::fromSnapshot($company, $this->logos->urls($company->logos));
    }
}
