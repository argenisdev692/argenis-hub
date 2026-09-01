<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

/**
 * Campaigns is a paid Meta Ads module — every goal here is acquisition or
 * retention-oriented, unlike SocialMedia's organic set (which also allows
 * 'viral'/'community'). Own Domain enum copy — modules never cross-import.
 */
enum CampaignBusinessGoal: string
{
    case Awareness = 'awareness';
    case Engagement = 'engagement';
    case Leads = 'leads';
    case Sales = 'sales';
    case Retention = 'retention';
}
