<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Score dimensions (plan §3.3, weights in config/lead-scout.php).
 */
enum SignalDimension: string
{
    case Technical = 'technical';
    case Commercial = 'commercial';
    case Remote = 'remote';
    case Communication = 'communication';
    case Recurrent = 'recurrent';
    case Vitality = 'vitality';
    case GeoContract = 'geo_contract';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
