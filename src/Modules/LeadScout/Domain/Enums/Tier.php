<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Score tier from explicit, configurable rules (spec US-4 CA-5).
 */
enum Tier: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case Discarded = 'discarded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
