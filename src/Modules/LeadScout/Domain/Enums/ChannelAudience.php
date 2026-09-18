<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Likely recipient behind a channel (spec §7 CanalDeContacto).
 */
enum ChannelAudience: string
{
    case LeadershipSales = 'leadership_sales';
    case HrRecruiting = 'hr_recruiting';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
