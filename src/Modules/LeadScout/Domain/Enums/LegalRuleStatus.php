<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Country-rule legal state (spec FR-44, clarify A18). All rules start
 * pending: blocking rules apply anyway, allowing rules do not.
 */
enum LegalRuleStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
