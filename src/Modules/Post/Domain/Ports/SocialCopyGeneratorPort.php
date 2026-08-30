<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\SocialCopyData;

/**
 * Generates LinkedIn + Instagram/Facebook copy for a chosen topic/angle.
 * Read-only — the caller decides whether/how to use the result.
 */
interface SocialCopyGeneratorPort
{
    public function generateSocialCopy(GenerateContentVariantData $data, ?object $causer = null): SocialCopyData;
}
