<?php

declare(strict_types=1);

namespace Modules\Company\Application\Queries;

use Modules\Company\Application\DTOs\CompanyProfileData;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;

/**
 * Reads the company for the authenticated operator (show + edit screens).
 *
 * Deliberately uncached: this is one indexed row behind an authenticated,
 * permission-gated screen that is opened a handful of times a day, and a stale
 * admin form is a far worse bug than a query. {@see GetPublicCompanyHandler} is
 * where caching earns its keep.
 */
final readonly class GetCompanyHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private CompanyLogoStoragePort $logos,
    ) {}

    #[\NoDiscard('handle() returns the company profile.')]
    public function handle(): CompanyProfileData
    {
        $company = $this->companies->current();

        return CompanyProfileData::fromSnapshot($company, $this->logos->urls($company->logos));
    }
}
