<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GeneratePostContentData;

/**
 * Kicks off the async quality loop for one generation row. Kept as its own
 * tiny port (rather than folded into {@see PostContentGeneratorPort}) so
 * Application depends only on this abstraction and never on the concrete
 * queued Job class (DIP) — the Infrastructure adapter is the only place that
 * knows a queue is involved at all.
 */
interface PostGenerationDispatcherPort
{
    public function dispatch(string $generationUuid, GeneratePostContentData $data, ?int $causerId = null): void;
}
