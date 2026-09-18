<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioRunEloquentModel;
use Modules\CvJobStudio\Infrastructure\Queue\HarvestStructuredSourcesJob;

/**
 * Starts a discovery run (T-043, FR-31 GAP-A2): the repository refuses a
 * profile missing a required gate input with `ProfileGateIncompleteException`
 * naming the input. The first job dispatches AFTER the run commits, so a
 * sync-queue worker never reads an uncommitted row.
 */
final readonly class StartRunHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    #[\NoDiscard]
    public function handle(string $profileUuid, int $userId): StudioRunEloquentModel
    {
        $run = $this->profiles->beginRun($profileUuid, $userId);

        HarvestStructuredSourcesJob::dispatch($run->id, $userId);

        return $run;
    }
}
