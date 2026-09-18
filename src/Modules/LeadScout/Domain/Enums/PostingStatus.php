<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Enums;

/**
 * A posting past the configured max age is expired and generates no leads
 * (spec US-2 CA-3, `lead-scout:expire`).
 */
enum PostingStatus: string
{
    case Active = 'active';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
