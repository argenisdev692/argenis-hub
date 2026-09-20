<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Queue;

use Modules\CvJobStudio\Domain\Ports\DiscoveryPipelinePort;

/** Harvest is the first stage; each job chains the next one itself. */
final readonly class QueuedDiscoveryPipeline implements DiscoveryPipelinePort
{
    public function start(int $runId, int $userId): void
    {
        HarvestStructuredSourcesJob::dispatch($runId, $userId);
    }
}
