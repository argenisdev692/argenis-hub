<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * Recorded reply outcome (spec FR-42): drives the stage move and, for
 * `unsubscribe`, the absolute suppression (FR-43).
 */
enum ReplyOutcome: string
{
    case Interested = 'interested';
    case NotInterested = 'not_interested';
    case Unsubscribe = 'unsubscribe';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
