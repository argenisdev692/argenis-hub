<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Modules\CvJobStudio\Application\DTOs\StudioBudgetData;
use Modules\CvJobStudio\Application\DTOs\StudioSourceData;

/** Read-only catalogue panels: spend per budget category and the source registry. */
interface StudioCatalogReadPort
{
    /** @return list<StudioBudgetData> */
    public function budgetsForPeriod(int $userId, string $period): array;

    /** @return list<StudioSourceData> in resolution order */
    public function sources(int $userId): array;
}
