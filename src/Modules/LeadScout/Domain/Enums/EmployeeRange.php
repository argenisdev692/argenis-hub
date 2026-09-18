<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Observed team size (spec US-4 CA-7: 1 person discards, 2-4 caps at B,
 * 5-50 preferred, > 200 discards, unknown lowers confidence).
 */
enum EmployeeRange: string
{
    case Solo = 'solo';
    case From2To4 = 'from_2_to_4';
    case From5To10 = 'from_5_to_10';
    case From11To50 = 'from_11_to_50';
    case From51To200 = 'from_51_to_200';
    case Over200 = 'over_200';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
