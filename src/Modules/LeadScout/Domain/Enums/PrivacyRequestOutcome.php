<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

enum PrivacyRequestOutcome: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
