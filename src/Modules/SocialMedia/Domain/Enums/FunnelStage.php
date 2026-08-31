<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

/**
 * TOFU/MOFU/BOFU classification the topic-ideation agent assigns to every
 * candidate topic. Drives which CTA rules the content-generation agent
 * applies — it is not cosmetic: TOFU never gets a hard-sell CTA, BOFU never
 * gets a soft "follow for more".
 */
enum FunnelStage: string
{
    case Tofu = 'tofu';
    case Mofu = 'mofu';
    case Bofu = 'bofu';
}
