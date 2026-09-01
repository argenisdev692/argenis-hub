<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Queue;

use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Domain\Ports\PostGenerationDispatcherPort;

/**
 * @see PostGenerationDispatcherPort
 */
final readonly class QueuedPostGenerationDispatcher implements PostGenerationDispatcherPort
{
    public function dispatch(string $generationUuid, GeneratePostContentData $data, ?int $causerId = null): void
    {
        GeneratePostContentJob::dispatch($generationUuid, $data, $causerId);
    }
}
