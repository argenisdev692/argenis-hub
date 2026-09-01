<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

/**
 * Meta Ads surface the campaign targets. 'Both' generates adapted copy for
 * Facebook AND Instagram in one attempt (see PlatformCampaignContentData) —
 * a single angle rarely needs a 3rd Meta surface, unlike SocialMedia's
 * 5-network spread, so this stays a flat enum rather than a multi-select.
 */
enum CampaignPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Both = 'both';
}
