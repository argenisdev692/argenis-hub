<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioInsightReportData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\StudioRunRepositoryPort;

/** The newest insight report of one of the owner's runs. */
final readonly class GetInsightReportHandler
{
    public function __construct(private StudioRunRepositoryPort $runs) {}

    #[\NoDiscard]
    public function handle(string $runUuid, int $userId): StudioInsightReportData
    {
        return $this->runs->latestInsightReport($runUuid, $userId)
            ?? throw RecordNotFoundException::of('report for run', $runUuid);
    }
}
