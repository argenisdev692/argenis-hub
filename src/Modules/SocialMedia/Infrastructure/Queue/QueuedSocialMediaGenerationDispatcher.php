<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Queue;

use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Domain\Ports\SocialMediaGenerationDispatcherPort;

/**
 * @see SocialMediaGenerationDispatcherPort
 */
final readonly class QueuedSocialMediaGenerationDispatcher implements SocialMediaGenerationDispatcherPort
{
    public function dispatch(string $contentUuid, GenerateSocialMediaContentData $data, ?int $causerId = null): void
    {
        GenerateSocialMediaContentJob::dispatch($contentUuid, $data, $causerId);
    }
}
