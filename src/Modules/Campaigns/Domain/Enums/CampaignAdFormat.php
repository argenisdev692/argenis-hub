<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

/**
 * Meta Ads placement/format. `LeadForm` targets Meta's native Instant Forms
 * — the single highest-intent lead-gen unit on Meta — and is the format the
 * generation agent leans on when `business_goal` is Leads.
 */
enum CampaignAdFormat: string
{
    case Feed = 'feed';
    case Story = 'story';
    case Reel = 'reel';
    case Carousel = 'carousel';
    case LeadForm = 'lead_form';
}
