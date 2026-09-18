<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Web vitality (spec FR-36): active ≤ 12 m, stale 12-24 m, inactive > 24 m,
 * unknown = no date found (never discards by itself).
 */
enum ActivityStatus: string
{
    case Active = 'active';
    case Stale = 'stale';
    case Inactive = 'inactive';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
