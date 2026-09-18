<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Why a lead was discarded (spec US-4 CA-9: every discard keeps its reason).
 */
enum DiscardReason: string
{
    case Suppressed = 'suppressed';
    case LargeOutsourcer = 'large_outsourcer';
    case LowTechnical = 'low_technical';
    case OnsiteAbroad = 'onsite_abroad';
    case SoloFreelancer = 'solo_freelancer';
    case Inactive = 'inactive';
    case DeadOrAcquired = 'dead_or_acquired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
