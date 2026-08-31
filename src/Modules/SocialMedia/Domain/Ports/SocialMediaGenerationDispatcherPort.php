<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;

/**
 * Kicks off the async quality-loop for one content row. Kept as its own tiny
 * port (rather than folding into {@see SocialMediaContentGeneratorPort}) so
 * Application depends only on this abstraction and never on the concrete
 * queued Job class (DIP) — the Infrastructure adapter is the only place that
 * knows a queue is involved at all.
 */
interface SocialMediaGenerationDispatcherPort
{
    public function dispatch(string $contentUuid, GenerateSocialMediaContentData $data, ?int $causerId = null): void;
}
