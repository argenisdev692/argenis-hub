<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;

/**
 * Step 2, rendering half: turns the image concepts and CapCut scripts of ONE
 * winning draft into stored artwork (cover + 5 platform graphics) and, on
 * TikTok / Instagram, a voiceover track.
 *
 * Called EXACTLY ONCE per generation, after the quality loop has chosen its
 * best draft — never per iteration. Rendering inside the loop meant a run that
 * used all 5 attempts billed up to 30 images and 5 voiceovers to keep 6 images
 * and 1 voiceover; every rejected attempt paid the most expensive part of the
 * pipeline for output that was thrown away.
 *
 * Best-effort by contract: a provider failure degrades to null paths (copy and
 * prompts stay usable) instead of failing the generation.
 */
interface SocialMediaAssetRendererPort
{
    public function render(
        string $contentUuid,
        GeneratedSocialMediaContentData $draft,
        GenerateSocialMediaContentData $data,
        ?object $causer = null,
    ): GeneratedSocialMediaContentData;
}
