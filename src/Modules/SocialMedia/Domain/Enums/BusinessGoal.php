<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Enums;

enum BusinessGoal: string
{
    case Awareness = 'awareness';
    case Engagement = 'engagement';
    case Viral = 'viral';
    case Leads = 'leads';
    case Sales = 'sales';
    case Community = 'community';
}
