<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * GDPR arts. 15-17/21 request kinds, handled by console command (spec FR-29).
 */
enum PrivacyRequestType: string
{
    case Access = 'access';
    case Erasure = 'erasure';
    case Objection = 'objection';
    case Rectification = 'rectification';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
