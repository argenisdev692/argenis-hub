<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\GenerateContentVariantData;
use Modules\Post\Application\DTOs\ReelPackageData;

/**
 * Generates a Reel/TikTok package (timeline, clean script, sound cue,
 * caption/hashtags) plus an AI voiceover for a chosen topic/angle. Read-only
 * — the caller decides whether/how to use the result.
 */
interface ReelPackageGeneratorPort
{
    public function generateReelPackage(GenerateContentVariantData $data, ?object $causer = null): ReelPackageData;
}
