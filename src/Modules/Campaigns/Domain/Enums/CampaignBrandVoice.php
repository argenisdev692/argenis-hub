<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Enums;

enum CampaignBrandVoice: string
{
    case Professional = 'professional';
    case Conversational = 'conversational';
    case Trendy = 'trendy';
    case Inspirational = 'inspirational';
    case Humorous = 'humorous';
}
