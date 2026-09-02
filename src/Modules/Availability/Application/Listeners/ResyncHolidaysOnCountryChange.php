<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Listeners;

use Modules\Availability\Domain\Services\HolidayMaterializer;
use Modules\Company\Domain\Events\CompanyCountryChanged;

/**
 * Rebuilds future national holidays whenever the company relocates to a new
 * country. Runs synchronously (a rare admin action) so the refreshed exceptions
 * are visible as soon as the settings screen reloads.
 */
final readonly class ResyncHolidaysOnCountryChange
{
    public function __construct(private HolidayMaterializer $materializer) {}

    public function handle(CompanyCountryChanged $event): void
    {
        $this->materializer->resync($event->currentCountryCode);
    }
}
