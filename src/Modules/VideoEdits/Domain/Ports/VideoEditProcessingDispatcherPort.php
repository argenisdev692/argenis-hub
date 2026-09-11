<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

/**
 * Hands an edit to the background processor (AD-15). The Application layer
 * never knows which queue, connection or job class does the work.
 */
interface VideoEditProcessingDispatcherPort
{
    public function dispatch(string $videoEditUuid): void;
}
