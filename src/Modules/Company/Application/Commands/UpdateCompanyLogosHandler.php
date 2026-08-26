<?php

declare(strict_types=1);

namespace Modules\Company\Application\Commands;

use Modules\Company\Application\DTOs\CompanyLogosData;
use Modules\Company\Domain\Enums\LogoVariant;
use Modules\Company\Domain\Ports\CompanyLogoStoragePort;
use Modules\Company\Domain\Ports\CompanyRepositoryPort;
use Shared\Domain\Ports\AuditPort;
use SplFileInfo;

/**
 * Replaces one or more brand marks.
 *
 * The ordering below is the whole point of the class: every new object is stored
 * first, the row is pointed at the new keys second, and only then are the
 * superseded objects removed. Deleting first would leave every email and landing
 * page rendering a broken image for as long as the upload takes, and a failure
 * between the two steps would leave the record pointing at an object that no
 * longer exists. Losing an orphaned object in the bucket is the cheapest
 * failure available here.
 *
 * Unlike a plain field edit this one does get an explicit `AuditPort` entry:
 * the attribute diff the model records shows one opaque storage key replacing
 * another, which tells a reader nothing about which marks were replaced.
 */
final readonly class UpdateCompanyLogosHandler
{
    public function __construct(
        private CompanyRepositoryPort $companies,
        private CompanyLogoStoragePort $logos,
        private AuditPort $audit,
    ) {}

    /**
     * @param  array<string, SplFileInfo>  $files  {@see LogoVariant} value → uploaded file
     */
    #[\NoDiscard('handle() returns the refreshed logo URLs.')]
    public function handle(array $files): CompanyLogosData
    {
        $current = $this->companies->current();

        $stored = [];
        $superseded = [];

        foreach ($files as $variant => $file) {
            $variant = LogoVariant::from($variant);

            $stored[$variant->value] = $this->logos->store($variant, $file);
            $superseded[] = $current->logo($variant);
        }

        $saved = $this->companies->save($current->withLogos($stored));

        foreach ($superseded as $key) {
            $this->logos->delete($key);
        }

        $this->audit->log(
            event: 'company.logos_updated',
            properties: [
                'uuid' => $saved->uuid,
                'variants' => array_keys($stored),
            ],
            logName: 'company.data',
        );

        return CompanyLogosData::fromUrls($this->logos->urls($saved->logos));
    }
}
