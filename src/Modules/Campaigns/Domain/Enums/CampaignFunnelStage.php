<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

/**
 * TOFU/MOFU/BOFU/LOYALTY classification the topic-ideation agent assigns to
 * every candidate Meta Ads angle. Drives which CTA and Meta objective rules
 * the generation agent applies — Loyalty is the 4th stage (retention/
 * retargeting) real Meta Ads accounts budget separately from acquisition,
 * per 2026 full-funnel playbooks (60-70% TOFU / 20-30% MOFU / 5-10% BOFU /
 * 5-10% Loyalty budget split).
 */
enum CampaignFunnelStage: string
{
    case Tofu = 'tofu';
    case Mofu = 'mofu';
    case Bofu = 'bofu';
    case Loyalty = 'loyalty';
}
