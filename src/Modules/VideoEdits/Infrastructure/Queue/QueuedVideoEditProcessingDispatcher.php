<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Queue;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\VideoEdits\Domain\Ports\VideoEditProcessingDispatcherPort;

final readonly class QueuedVideoEditProcessingDispatcher implements VideoEditProcessingDispatcherPort
{
    public function __construct(
        private Config $config,
    ) {}

    public function dispatch(string $videoEditUuid): void
    {
        ProcessVideoEditJob::dispatch($videoEditUuid)
            ->onConnection((string) $this->config->get('video-edit.queue.connection'))
            ->onQueue((string) $this->config->get('video-edit.queue.name'));
    }
}
