<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\CvJobStudio\Application\DTOs\StudioBudgetData;
use Modules\CvJobStudio\Domain\Ports\StudioCatalogReadPort;

/** Spend vs limit per category for the current period (FR-33, NFR-4). */
final readonly class GetBudgetStatusHandler
{
    public function __construct(private StudioCatalogReadPort $catalog) {}

    /** @return list<StudioBudgetData> */
    #[\NoDiscard]
    public function handle(int $userId): array
    {
        return $this->catalog->budgetsForPeriod($userId, CarbonImmutable::now()->format('Y-m'));
    }
}
