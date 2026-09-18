<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * An inference weighs less than a fact with identical content
 * (spec US-4 CA-3, inference × 0.6).
 */
enum SignalNature: string
{
    case Fact = 'fact';
    case Inference = 'inference';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
