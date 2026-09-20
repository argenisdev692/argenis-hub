<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

interface DiscoveryPipelinePort
{
    /** Queues the first stage of a committed discovery run. */
    public function start(int $runId, int $userId): void;
}
