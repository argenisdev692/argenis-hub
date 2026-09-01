<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;

/**
 * Everything billed by the image and speech providers, behind one port,
 * invoked ONCE per generation — after the quality loop has already picked its
 * winner.
 *
 * Keeping it out of {@see CampaignGeneratorPort} is what makes a rejected
 * attempt cheap. A 5-iteration run used to render a cover plus one image per
 * platform variant on EVERY attempt — up to 15 billed images to keep 3 — and
 * the voiceover the Reels/Stories package needs was never produced at all.
 *
 * Best-effort by contract: a provider failure yields a null path rather than
 * losing a finished campaign because an image or TTS endpoint hiccuped. By the
 * time this runs the copy is already final.
 */
interface CampaignAssetRendererPort
{
    public function render(
        string $campaignUuid,
        CampaignDraftData $draft,
        GenerateCampaignData $data,
        ?object $causer = null,
    ): CampaignDraftData;
}
