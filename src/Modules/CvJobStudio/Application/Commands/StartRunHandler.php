<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Commands;

use Modules\CvJobStudio\Application\DTOs\StudioRunData;
use Modules\CvJobStudio\Domain\Exceptions\RecordNotFoundException;
use Modules\CvJobStudio\Domain\Ports\DiscoveryPipelinePort;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;

/**
 * Starts a discovery run (T-043, FR-31 GAP-A2): the repository refuses a
 * profile missing a required gate input with `ProfileGateIncompleteException`
 * naming the input. The first job dispatches AFTER the run commits, so a
 * sync-queue worker never reads an uncommitted row.
 */
final readonly class StartRunHandler
{
    public function __construct(
        private StudioProfileRepositoryPort $profiles,
        private DiscoveryPipelinePort $pipeline,
    ) {}

    #[\NoDiscard]
    public function handle(string $profileUuid, int $userId): StudioRunData
    {
        $started = $this->profiles->beginRun($profileUuid, $userId)
            ?? throw RecordNotFoundException::of('profile', $profileUuid);

        $this->pipeline->start($started['id'], $userId);

        return $started['run'];
    }
}
